<?php

namespace App\Exports\Konsinyasi;

use App\Models\Konsinyasi\ConsignmentItem;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ConsignmentItemsExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithEvents,
    WithTitle,
    WithCustomStartCell,
    WithColumnFormatting,
    ShouldAutoSize
{
    use Exportable;

    private int $rowNumber = 0;

    public function __construct(
        protected int $storeId,
        protected array $filters = []
    ) {}

    public function title(): string
    {
        return 'Barang Konsinyasi';
    }

    public function startCell(): string
    {
        return 'A4';
    }

    public function query(): Builder
    {
        $query = ConsignmentItem::query()
            ->with(['product', 'supplier', 'defaultWarehouse', 'stockBalances'])
            ->where('store_id', $this->storeId);

        $search = $this->filters['search'] ?? $this->filters['keyword'] ?? null;
        if (! empty($search)) {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->whereHas('product', function (Builder $product) use ($search) {
                        $product->where('name', 'like', '%'.$search.'%')
                            ->orWhere('sku', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('supplier', function (Builder $supplier) use ($search) {
                        $supplier->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        if (! empty($this->filters['supplier_id'])) {
            $query->where('supplier_id', $this->filters['supplier_id']);
        }

        if (! empty($this->filters['warehouse_id'])) {
            $query->where('default_warehouse_id', $this->filters['warehouse_id']);
        }

        $status = $this->filters['status'] ?? null;
        if (! empty($status) && $status !== 'all') {
            $query->where('status', strtolower((string) $status));
        }

        if (! empty($this->filters['date_start'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_start']);
        }

        if (! empty($this->filters['date_end'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_end']);
        }

        return $query->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'No',
            'SKU',
            'Nama Produk',
            'Supplier',
            'Gudang',
            'Harga Jual',
            'Harga Konsinyasi',
            'Tipe Komisi',
            'Nilai Komisi',
            'Stok Tersedia',
            'Stok Terjual',
            'Stok Retur',
            'Status',
            'Tanggal Dibuat',
        ];
    }

    public function map($item): array
    {
        $this->rowNumber++;

        $status = match (strtolower((string) $item->status)) {
            'active' => 'Aktif',
            'inactive' => 'Tidak Aktif',
            'closed' => 'Selesai Masa Konsinyasi',
            default => (string) $item->status,
        };

        $commissionType = match ($item->commission_type) {
            'margin' => 'Margin',
            'percentage' => 'Persentase',
            'fixed' => 'Nominal Tetap',
            default => (string) $item->commission_type,
        };

        return [
            $this->rowNumber,
            $item->product->sku ?? '-',
            $item->product->name ?? '-',
            $item->supplier->name ?? '-',
            $item->defaultWarehouse->name ?? '-',
            (float) $item->selling_price,
            (float) $item->consignment_price,
            $commissionType,
            (float) $item->commission_value,
            (float) $item->stockBalances->sum('quantity_available'),
            (float) $item->stockBalances->sum('quantity_sold'),
            (float) $item->stockBalances->sum('quantity_returned'),
            $status,
            $item->created_at ? Date::dateTimeToExcel($item->created_at) : null,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => '"Rp" #,##0',
            'G' => '"Rp" #,##0',
            'I' => '"Rp" #,##0',
            'J' => '#,##0',
            'K' => '#,##0',
            'L' => '#,##0',
            'N' => NumberFormat::FORMAT_DATE_DATETIME,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1E1B4B']],
            ],
            2 => [
                'font' => ['size' => 9, 'color' => ['rgb' => '64748B']],
            ],
            4 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(4, $sheet->getHighestRow());

                $sheet->mergeCells('A1:N1');
                $sheet->setCellValue('A1', 'Daftar Barang Konsinyasi');

                $sheet->mergeCells('A2:N2');
                $sheet->setCellValue(
                    'A2',
                    'Diekspor '.now()->timezone('Asia/Jakarta')->format('d/m/Y H:i')
                    .' · data mengikuti filter daftar'
                );

                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(4)->setRowHeight(22);

                $sheet->freezePane('A5');
                $sheet->setAutoFilter('A4:N4');

                $sheet->getStyle('A4:N'.$lastRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'E2E8F0'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('F5:G'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('I5:L'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('A5:A'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('M5:M'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                for ($row = 5; $row <= $lastRow; $row++) {
                    if ($row % 2 === 1) {
                        $sheet->getStyle('A'.$row.':N'.$row)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F8FAFC');
                    }

                    $status = (string) $sheet->getCell('M'.$row)->getValue();
                    $color = match ($status) {
                        'Aktif' => 'D1FAE5',
                        'Tidak Aktif' => 'FEF3C7',
                        'Selesai Masa Konsinyasi' => 'E2E8F0',
                        default => null,
                    };

                    if ($color) {
                        $sheet->getStyle('M'.$row)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB($color);
                    }
                }

                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToPage(true);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.4);
                $sheet->getPageMargins()->setLeft(0.3);
                $sheet->getPageMargins()->setRight(0.3);
            },
        ];
    }
}
