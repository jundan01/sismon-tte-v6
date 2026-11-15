<?php

namespace App\Mail;

use App\Domain\Sertifikat\Models\SertifikatTte;
use App\Services\Security\CryptoHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable Notifikasi Peringatan Kedaluwarsa Sertifikat Elektronik (H-7).
 * Ref: implementation.md §4.6.3 (V6.5)
 */
class SertifikatExpiringNotification extends Mailable
{
    use Queueable, SerializesModels;

    public string $namaPemohon;
    public string $maskedNip;
    public string $maskedNik;
    public string $namaSkpd;
    public int $sisaHari;
    public string $tanggalKadaluarsa;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SertifikatTte $sertifikat,
        ?int $customSisaHari = null
    ) {
        $pendaftaran = $sertifikat->pendaftaran;

        $this->namaPemohon = $pendaftaran?->nama_lengkap ?? 'Pemilik Sertifikat TTE';
        $this->namaSkpd = $pendaftaran?->skpd?->nama_skpd ?? 'Pemerintah Kota Cirebon';

        $rawNik = $pendaftaran?->nik ? (CryptoHelper::decryptField($pendaftaran->nik) ?? $pendaftaran->nik) : null;
        $rawNip = $pendaftaran?->nip ? (CryptoHelper::decryptField($pendaftaran->nip) ?? $pendaftaran->nip) : null;

        $this->maskedNik = $rawNik ? (strlen($rawNik) >= 8 ? substr($rawNik, 0, 6) . '******' . substr($rawNik, -4) : $rawNik) : '-';
        $this->maskedNip = $rawNip ? (strlen($rawNip) >= 8 ? substr($rawNip, 0, 6) . '******' . substr($rawNip, -4) : $rawNip) : '-';

        $this->sisaHari = $customSisaHari ?? ($sertifikat->sisa_hari ?? 7);
        $this->tanggalKadaluarsa = $sertifikat->tanggal_kadaluarsa ? $sertifikat->tanggal_kadaluarsa->isoFormat('D MMMM Y') : '-';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[PENTING] Peringatan Masa Berlaku Sertifikat Elektronik TTE (H-{$this->sisaHari}) - Diskominfo Kota Cirebon",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.sertifikat_expiring',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
