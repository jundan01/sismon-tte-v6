<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\Models\AuditActionHistory;
use App\Domain\Master\Models\SystemParameter;
use App\Services\SystemParameterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Controller Pengaturan Parameter Kebijakan Sistem (Anti Hard-Coding).
 * Ref: implementation.md §3.1.C & §4.2.1
 */
class SystemParameterController extends Controller
{
    /**
     * Tampilkan panel pengaturan parameter kebijakan sistem.
     */
    public function index(): View
    {
        if (!auth()->user()?->isSuperadmin()) {
            abort(403, 'Akses Terbatas: Hanya Superadmin yang berwenang mengakses konfigurasi parameter kebijakan sistem.');
        }

        $parameters = SystemParameter::orderBy('param_group')
            ->orderBy('id')
            ->get();

        $groupedParameters = $parameters->groupBy('param_group');

        return view('admin.settings.parameters', [
            'groupedParameters' => $groupedParameters,
            'allParameters'     => $parameters,
        ]);
    }

    /**
     * Perbarui nilai parameter kebijakan sistem.
     */
    public function update(Request $request): RedirectResponse
    {
        if (!auth()->user()?->isSuperadmin()) {
            abort(403, 'Akses Terbatas: Hanya Superadmin yang berwenang mengubah konfigurasi parameter kebijakan sistem.');
        }

        $validated = $request->validate([
            'parameters'                                => ['required', 'array'],
            'parameters.cert_validity_years'            => ['nullable', 'integer', 'min:1', 'max:10'],
            'parameters.cert_expiry_warning_days'       => ['nullable', 'integer', 'min:1', 'max:30'],
            'parameters.allow_non_asn_registration'     => ['nullable'],
            'parameters.bulk_verification_max_batch'    => ['nullable', 'integer', 'min:5', 'max:200'],
            'parameters.city_adoption_target_percent'   => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'parameters.cert_validity_years.min'          => 'Masa berlaku sertifikat minimal 1 tahun.',
            'parameters.cert_validity_years.max'          => 'Masa berlaku sertifikat maksimal 10 tahun.',
            'parameters.cert_expiry_warning_days.min'     => 'Ambang peringatan kadaluarsa minimal 1 hari.',
            'parameters.cert_expiry_warning_days.max'     => 'Ambang peringatan kadaluarsa maksimal 30 hari.',
            'parameters.bulk_verification_max_batch.min'  => 'Batas masal per batch minimal 5 permohonan.',
            'parameters.bulk_verification_max_batch.max'  => 'Batas masal per batch maksimal 200 permohonan.',
            'parameters.city_adoption_target_percent.min' => 'Target adopsi kota minimal 1%.',
            'parameters.city_adoption_target_percent.max' => 'Target adopsi kota maksimal 100%.',
        ]);

        $paramsInput = $validated['parameters'];

        // Tangani checkbox allow_non_asn_registration
        $paramsInput['allow_non_asn_registration'] = isset($paramsInput['allow_non_asn_registration']) && in_array($paramsInput['allow_non_asn_registration'], ['1', 'true', true], true)
            ? 'true'
            : 'false';

        DB::transaction(function () use ($paramsInput) {
            $previousState = [];
            $newState = [];

            foreach ($paramsInput as $key => $value) {
                if ($value === null) {
                    continue;
                }

                $param = SystemParameter::where('param_key', $key)->first();
                if ($param) {
                    $previousState[$key] = $param->param_value;
                    $param->update([
                        'param_value' => (string) $value,
                    ]);
                    $newState[$key] = (string) $value;
                }
            }

            // Catat log audit perubahan parameter
            AuditActionHistory::create([
                'action_type'    => 'UPDATE_SYSTEM_PARAMETERS',
                'entity_type'    => 'SystemParameter',
                'entity_id'      => 'system_parameters_batch',
                'user_id'        => auth()->id(),
                'previous_state' => $previousState,
                'new_state'      => $newState,
                'is_undone'      => false,
            ]);

            // Bersihkan cache parameter dan makro stats
            SystemParameterService::clearCache();
            cache()->forget('public_portal_macro_stats');
        });

        return redirect()->route('admin.settings.parameters')
            ->with('success', 'Parameter kebijakan sistem berhasil diperbarui dan langsung aktif di seluruh layanan.');
    }
}
