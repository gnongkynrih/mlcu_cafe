<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds the Sale Report spreadsheet (.xlsx).
 *
 * Laravel Excel works with "concerns": small interfaces we add to the class.
 * Each one we implement switches on one feature of the spreadsheet:
 *
 *  - FromCollection       -> collection()    gives the rows of data
 *  - WithHeadings         -> headings()      gives the first (header) row
 *  - WithColumnFormatting -> columnFormats() tells Excel how to display dates and money
 *  - WithStyles           -> styles()        colours the header, stripes the rows, highlights the total
 *  - WithTitle            -> title()         names the sheet tab at the bottom of Excel
 *  - ShouldAutoSize       -> (no method)     widens every column to fit its text
 *
 * The same class handles both report types:
 *
 *  Summary: | Date        | Total (₹) |
 *  Detail:  | Paid At     | Razorpay Reference | Amount (₹) |
 */
class OrderExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    /**
     * Excel number format for money, e.g. 1234.5 is shown as 1,234.50.
     */
    private const MONEY_FORMAT = '#,##0.00';

    /*
     * Colours are written as ARGB hex: the first two letters are opacity (FF = solid),
     * then red, green and blue, like a CSS colour.
     */
    private const HEADER_COLOUR = 'FFF59E0B';   // amber, the same as the report page

    private const TOTAL_COLOUR = 'FFFEF3C7';    // pale amber

    private const STRIPE_COLOUR = 'FFFAFAF9';   // very light grey

    private const BORDER_COLOUR = 'FFD6D3D1';   // soft grey

    /**
     * Constructor property promotion: writing "private" in front of each
     * parameter creates $this->orders and $this->reportType for us.
     *
     * @param  Collection<int, Order>  $orders  the rows the report page already queried
     * @param  string  $reportType  either "summary" or "details"
     */
    public function __construct(
        private Collection $orders,
        private string $reportType,
    ) {}

    /**
     * Is this the summary report (one row per day)?
     */
    private function isSummary(): bool
    {
        return $this->reportType === 'summary';
    }

    /**
     * The header row. Laravel Excel writes it as row 1.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        if ($this->isSummary()) {
            return ['Date', 'Total (₹)'];
        }

        return ['Paid At', 'Razorpay Reference', 'Amount (₹)'];
    }

    /**
     * Every row under the header: one row per order, then a "Total" row.
     *
     * Each row is a plain array, and each value in it fills one column (A, B, C...).
     *
     * @return Collection<int, array<int, float|string>>
     */
    public function collection(): Collection
    {
        // map() turns each Order model into an array of cell values.
        $rows = $this->orders->map(fn (Order $order): array => $this->row($order));

        // push() adds the total row at the very end.
        return $rows->push($this->totalRow());
    }

    /**
     * Turn one order into one spreadsheet row.
     *
     * Dates are converted with Date::dateTimeToExcel(). Excel stores a date as a
     * number (days since 1900), so giving it a real date instead of text means
     * people can sort and filter the column properly.
     *
     * @return array<int, float|string>
     */
    private function row(Order $order): array
    {
        $paidAt = Date::dateTimeToExcel($order->paid_at);

        if ($this->isSummary()) {
            return [$paidAt, (float) $order->total_amount];
        }

        return [
            $paidAt,
            $order->razorpay_payment_id ?: '—', // cash and UPI payments have no Razorpay reference
            (float) $order->total_amount,
        ];
    }

    /**
     * The last row: the word "Total" and the sum of every amount.
     *
     * @return array<int, float|string>
     */
    private function totalRow(): array
    {
        $grandTotal = (float) $this->orders->sum('total_amount');

        if ($this->isSummary()) {
            return ['Total', $grandTotal];
        }

        // The empty string leaves the Razorpay Reference cell blank.
        return ['Total', '', $grandTotal];
    }

    /**
     * How Excel should display each column. The keys are column letters.
     *
     * Formatting only changes how a value looks, not the value itself, so the
     * amounts are still real numbers you can add up inside Excel.
     *
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        if ($this->isSummary()) {
            return [
                'A' => 'dd mmm yyyy',              // 29 Oct 2026
                'B' => self::MONEY_FORMAT,
            ];
        }

        return [
            'A' => 'dd mmm yyyy, hh:mm AM/PM',     // 29 Oct 2026, 09:15 AM
            'C' => self::MONEY_FORMAT,
        ];
    }

    /**
     * Style the whole sheet so it reads like the report on screen.
     *
     *  - Header row:   white bold text on the cafe's amber colour, centred
     *  - Order rows:   light stripes on every other row so long lists are easy to follow
     *  - Total row:    bold on a pale amber fill, with a double line above it
     *  - Every cell:   a thin grey border, like a printed table
     *  - Money column: right aligned, so the decimal points line up
     *
     * It also freezes the header row (it stays visible while you scroll) and
     * adds filter arrows to it, so people can sort by date or amount in Excel.
     *
     * The total row number = 1 header row + number of orders + 1.
     * For example, 3 orders fill rows 2, 3 and 4, so the total is in row 5.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $lastColumn = $this->isSummary() ? 'B' : 'C';     // the money column is always the last one
        $lastOrderRow = $this->orders->count() + 1;
        $totalRowNumber = $lastOrderRow + 1;

        // Keep row 1 on screen while scrolling (Excel's "Freeze Top Row").
        $sheet->freezePane('A2');

        // Filter arrows on the header. The total row is left out, so sorting never moves it.
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastOrderRow}");

        // A little taller than normal, so the header has room to breathe.
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Light stripes on every second order row (rows 3, 5, 7...).
        for ($row = 3; $row <= $lastOrderRow; $row += 2) {
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB(self::STRIPE_COLOUR);
        }

        // The keys below are row numbers or cell ranges. Laravel Excel applies each style array to them.
        return [
            // Every cell in the table, header to total: thin grey borders and vertically centred text.
            "A1:{$lastColumn}{$totalRowNumber}" => [
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::BORDER_COLOUR]],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ],

            // Header row.
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_COLOUR]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],

            // Money column, from the first order row down to the total.
            "{$lastColumn}2:{$lastColumn}{$totalRowNumber}" => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],

            // Total row.
            $totalRowNumber => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::TOTAL_COLOUR]],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE]],
            ],
        ];
    }

    /**
     * The name on the sheet tab at the bottom of Excel.
     */
    public function title(): string
    {
        return $this->isSummary() ? 'Summary' : 'Detail';
    }
}
