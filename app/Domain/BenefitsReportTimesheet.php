<?php

declare(strict_types=1);

namespace Toby\Domain;

use Generator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Toby\Eloquent\Models\BenefitsReport;

class BenefitsReportTimesheet implements WithTitle, WithHeadings, WithEvents, WithStyles, WithStrictNullComparison, FromGenerator
{
    use RegistersEventListeners;

    protected const ROW_HEIGHT = 70;
    protected const COLUMN_WIDTH = 22;

    public function __construct(
        protected BenefitsReport $report,
        protected array $userIds,
    ) {}

    public function title(): string
    {
        return Str::slug($this->report->name);
    }

    public function generator(): Generator
    {
        $data = Arr::where(
            $this->report->data,
            fn(array $item): bool => in_array($item["user"], $this->userIds, false),
        );

        $data = Arr::map($data, function (array $item): array {
            $user = Arr::first($this->report->users, fn(array $user): bool => $user["id"] === $item["user"]);

            return [
                "user" => $user["name"],
                "benefits" => Arr::map($item["benefits"], function (array $benefit): array {
                    $foundBenefit = Arr::first(
                        $this->report->benefits,
                        fn(array $find): bool => $find["id"] === $benefit["id"],
                    );

                    return [
                        "name" => $foundBenefit["name"],
                        "employer" => $benefit["employer"],
                        "employee" => $benefit["employee"],
                        "companion" => $foundBenefit["companion"],
                    ];
                }),
            ];
        });

        foreach ($data as $item) {
            $row = [$item["user"]];

            foreach ($item["benefits"] as $benefit) {
                if (!$benefit["companion"]) {
                    $row[] = $benefit["employer"] ? $benefit["employer"] / 100 : null;
                }
                $row[] = $benefit["employee"] ? $benefit["employee"] / 100 : null;
            }

            yield $row;
        }
    }

    public function headings(): array
    {
        $headings = [];

        $headings[] = __("First name and last name");

        foreach ($this->report->benefits as $benefit) {
            if (!$benefit["companion"]) {
                $headings[] = $benefit["name"] . " - " . __("employer's expense");
            }
            $headings[] = $benefit["name"] . " - " . __("payroll");
        }

        return $headings;
    }

    public function styles(Worksheet $sheet): void
    {
        $lastRow = $sheet->getHighestRow();
        $lastColumn = $sheet->getHighestColumn();

        $sheet->getStyle("A1:{$lastColumn}1")
            ->getFont()
            ->setBold(true);

        $sheet->getStyle("A1:{$lastColumn}1")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("A1:{$lastColumn}1")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB("D9D9D9");

        $sheet->getStyle("A2:A{$lastRow}")
            ->getFont()
            ->setBold(true);

        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()
            ->setRGB("B7B7B7");

        $sheet->getStyle("B2:{$lastColumn}{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        for ($column = 1; $column <= Coordinate::columnIndexFromString($lastColumn); $column++) {
            $sheet->getColumnDimensionByColumn($column)->setWidth(static::COLUMN_WIDTH);
        }

        $sheet->getRowDimension(1)
            ->setRowHeight(static::ROW_HEIGHT);

        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
            ->getAlignment()
            ->setWrapText(true);
    }
}
