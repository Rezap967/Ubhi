<?php

// app/Services/Product/UnitValidationService.php

namespace App\Services\Products;

use App\Repositories\Products\UnitRepository;

class UnitValidationService
{
    public function __construct(
        protected UnitRepository $unitRepository
    ) {}

    /**
     * Cek apakah satuan masih dipakai di 4 tabel terkait.
     * Return array counts, plus 'in_use' boolean untuk kemudahan pengecekan.
     */
    public function checkUsage(int $unitId): array
    {
        $usage = $this->unitRepository->checkUsage($unitId);

        $usage['in_use'] = array_sum($usage) > 0;

        return $usage;
    }
}
