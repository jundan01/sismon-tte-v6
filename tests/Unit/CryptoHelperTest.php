<?php

namespace Tests\Unit;

use App\Services\Security\CryptoHelper;
use Tests\TestCase;

class CryptoHelperTest extends TestCase
{
    /**
     * Uji 1: Round-trip enkripsi dan dekripsi dengan label kolom.
     */
    public function test_can_encrypt_and_decrypt_sensitive_field(): void
    {
        $nik = '3274010101900001';
        $nip = '199001012020121001';

        $cipherNik = CryptoHelper::encryptField($nik, 'nik');
        $cipherNip = CryptoHelper::encryptField($nip, 'nip');

        $this->assertNotNull($cipherNik);
        $this->assertNotNull($cipherNip);
        $this->assertNotEquals($nik, $cipherNik);
        $this->assertNotEquals($nip, $cipherNip);

        // Dekripsi dengan label konteks yang sesuai harus berhasil
        $this->assertEquals($nik, CryptoHelper::decryptField($cipherNik, 'nik'));
        $this->assertEquals($nip, CryptoHelper::decryptField($cipherNip, 'nip'));

        // Dekripsi dengan label konteks yang salah harus ditolak (MAC mismatch)
        $this->assertNull(CryptoHelper::decryptField($cipherNik, 'nip'), 'Dekripsi NIK dengan label NIP harus ditolak.');
        $this->assertNull(CryptoHelper::decryptField($cipherNip, 'nik'), 'Dekripsi NIP dengan label NIK harus ditolak.');
    }

    /**
     * Uji 2: Deterministik untuk plaintext dan konteks kolom yang sama.
     */
    public function test_encrypt_field_produces_consistent_deterministic_ciphertext(): void
    {
        $nik = '3274010101900001';
        $cipher1 = CryptoHelper::encryptField($nik, 'nik');
        $cipher2 = CryptoHelper::encryptField($nik, 'nik');

        $this->assertNotNull($cipher1);
        $this->assertEquals($cipher1, $cipher2, 'Ciphertext harus identik untuk plaintext dan label kolom yang sama.');
    }

    /**
     * Uji 3: Plaintext sama menghasilkan ciphertext berbeda jika label kolom berbeda.
     */
    public function test_different_column_labels_produce_distinct_ciphertexts(): void
    {
        $sameValue = '199001012020121001';
        $cipherAsNik = CryptoHelper::encryptField($sameValue, 'nik');
        $cipherAsNip = CryptoHelper::encryptField($sameValue, 'nip');

        $this->assertNotNull($cipherAsNik);
        $this->assertNotNull($cipherAsNip);
        $this->assertNotEquals($cipherAsNik, $cipherAsNip, 'Nilai yang sama harus menghasilkan ciphertext berbeda pada kolom nik vs nip.');
    }

    /**
     * Uji 4: Panjang ciphertext terverifikasi (NIK 33 byte / 44 b64 chars, NIP 35 byte / 48 b64 chars).
     */
    public function test_exact_ciphertext_lengths_match_specification(): void
    {
        $nik = '3274010101900001'; // 16 digit
        $nip = '199001012020121001'; // 18 digit

        $cipherNikB64 = CryptoHelper::encryptField($nik, 'nik');
        $cipherNipB64 = CryptoHelper::encryptField($nip, 'nip');

        $rawNik = base64_decode($cipherNikB64);
        $rawNip = base64_decode($cipherNipB64);

        // NIK: 1 byte versi + 16 byte IV + 16 byte CTR = 33 byte raw -> 44 karakter base64
        $this->assertEquals(33, strlen($rawNik), 'Ukuran biner NIK harus tepat 33 byte.');
        $this->assertEquals(44, strlen($cipherNikB64), 'Ukuran base64 NIK harus tepat 44 karakter.');

        // NIP: 1 byte versi + 16 byte IV + 18 byte CTR = 35 byte raw -> 48 karakter base64
        $this->assertEquals(35, strlen($rawNip), 'Ukuran biner NIP harus tepat 35 byte.');
        $this->assertEquals(48, strlen($cipherNipB64), 'Ukuran base64 NIP harus tepat 48 karakter.');
    }

    /**
     * Uji 5: Tamper pada setiap byte ciphertext (versi, IV, payload) wajib ditolak.
     */
    public function test_tamper_on_every_byte_is_strictly_rejected(): void
    {
        $nik = '3274010101900001';
        $cipherB64 = CryptoHelper::encryptField($nik, 'nik');
        $raw = base64_decode($cipherB64);

        $totalBytes = strlen($raw); // 33 byte
        $this->assertEquals(33, $totalBytes);

        // Uji pemalsuan (bit-flip) secara exhaustive pada setiap byte
        for ($i = 0; $i < $totalBytes; $i++) {
            $tamperedRaw = $raw;
            // Flip bit terakhir pada byte ke-$i
            $tamperedRaw[$i] = chr(ord($tamperedRaw[$i]) ^ 0x01);
            $tamperedB64 = base64_encode($tamperedRaw);

            $decrypted = CryptoHelper::decryptField($tamperedB64, 'nik');
            $this->assertNull(
                $decrypted,
                "Pemalsuan pada byte index {$i} (posisi " . ($i === 0 ? 'Versi' : ($i <= 16 ? 'IV' : 'CTR')) . ") wajib ditolak!"
            );
        }
    }

    /**
     * Uji 6: Dua NIP dengan 16 digit pertama identik menghasilkan 16 byte pertama ciphertext yang berbeda (Bebas Leakage).
     */
    public function test_two_nips_with_same_16_digit_prefix_produce_divergent_first_16_bytes(): void
    {
        $nip1 = '199001012020121001';
        $nip2 = '199001012020121002'; // Hanya berbeda pada digit ke-18

        // Skema Baru (v1 DAE: SIV-CTR)
        $rawNew1 = base64_decode(CryptoHelper::encryptField($nip1, 'nip'));
        $rawNew2 = base64_decode(CryptoHelper::encryptField($nip2, 'nip'));

        $prefixNew1 = substr($rawNew1, 0, 16);
        $prefixNew2 = substr($rawNew2, 0, 16);

        $this->assertNotEquals(
            $prefixNew1,
            $prefixNew2,
            'Skema BARU (CTR-SIV) wajib mengacak 16 byte pertama ciphertext meskipun 16 digit awal plaintext identik.'
        );

        // Skema Lama (v0: CBC static IV) membocorkan blok pertama (sebagai pembuktian kerentanan lama)
        $rawOld1 = base64_decode(CryptoHelper::encryptFieldLegacy($nip1));
        $rawOld2 = base64_decode(CryptoHelper::encryptFieldLegacy($nip2));

        $prefixOld1 = substr($rawOld1, 0, 16);
        $prefixOld2 = substr($rawOld2, 0, 16);

        $this->assertEquals(
            $prefixOld1,
            $prefixOld2,
            'Skema LAMA (CBC) membocorkan 16 byte pertama karena static IV.'
        );
    }

    /**
     * Uji 7: Dual-Read mampu mendekripsi ciphertext warisan v0 (AES-256-CBC).
     */
    public function test_dual_read_successfully_decrypts_legacy_v0_ciphertext(): void
    {
        $nik = '3274010101900001';
        $legacyCipher = CryptoHelper::encryptFieldLegacy($nik);

        $this->assertNotNull($legacyCipher);
        // Pastikan bukan diawali 0x01
        $decoded = base64_decode($legacyCipher);
        $this->assertNotEquals("\x01", $decoded[0]);

        // Dual-read dekripsi harus otomatis berhasil mengenali skema v0
        $decrypted = CryptoHelper::decryptField($legacyCipher, 'nik');
        $this->assertEquals($nik, $decrypted, 'Jalur dual-read wajib berhasil mendekripsi data warisan v0.');
    }

    /**
     * Uji 8: Modul Blind Index terisolasi khusus benchmark.
     */
    public function test_generates_deterministic_blind_index(): void
    {
        $nik = '3274010101900001';
        
        $hash1 = CryptoHelper::generateBlindIndex($nik);
        $hash2 = CryptoHelper::generateBlindIndex($nik);
        $hashDifferent = CryptoHelper::generateBlindIndex('3274010101900002');
        
        $this->assertNotNull($hash1);
        $this->assertEquals(64, strlen($hash1)); // SHA-256 hex string length
        $this->assertEquals($hash1, $hash2);
        $this->assertNotEquals($hash1, $hashDifferent);
    }

    /**
     * Uji 9: SHA-256 Checksum berkas fisik.
     */
    public function test_can_calculate_and_verify_file_checksum(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tte_test_');
        file_put_contents($tempFile, 'DOKUMEN_SK_PELANTIKAN_ASN_2026');
        
        $expectedChecksum = hash('sha256', 'DOKUMEN_SK_PELANTIKAN_ASN_2026');
        
        $calculated = CryptoHelper::calculateFileChecksum($tempFile);
        $this->assertEquals($expectedChecksum, $calculated);
        
        $isValid = CryptoHelper::verifyFileChecksum($tempFile, $expectedChecksum);
        $this->assertTrue($isValid);
        
        $isInvalid = CryptoHelper::verifyFileChecksum($tempFile, 'invalid_checksum_string_123456');
        $this->assertFalse($isInvalid);
        
        @unlink($tempFile);
    }

    /**
     * Uji 10: Signed payload dengan time-to-live.
     */
    public function test_signed_payload_creation_and_extraction(): void
    {
        $payload = ['user_id' => 10, 'action' => 'VERIFIKASI_EMAIL'];
        
        $token = CryptoHelper::createSignedPayload($payload, 300);
        $this->assertNotNull($token);
        
        $extracted = CryptoHelper::extractSignedPayload($token);
        $this->assertIsArray($extracted);
        $this->assertEquals(10, $extracted['user_id']);
        $this->assertEquals('VERIFIKASI_EMAIL', $extracted['action']);
    }
}
