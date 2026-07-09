<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AppointmentsXlsxExporter
{
    /**
     * @param Collection<int, Appointment> $appointments
     * @param array<string, mixed> $metrics
     * @param array<string, string> $statusOptions
     */
    public function stream(Collection $appointments, array $metrics, array $statusOptions, string $periodLabel, Carbon $generatedAt, ?User $generatedBy, string $timezone): void
    {
        echo $this->build($appointments, $metrics, $statusOptions, $periodLabel, $generatedAt, $generatedBy, $timezone);
    }

    /**
     * @param Collection<int, Appointment> $appointments
     * @param array<string, mixed> $metrics
     * @param array<string, string> $statusOptions
     */
    public function build(Collection $appointments, array $metrics, array $statusOptions, string $periodLabel, Carbon $generatedAt, ?User $generatedBy, string $timezone): string
    {
        return $this->zip($this->files($appointments, $metrics, $statusOptions, $periodLabel, $generatedAt, $generatedBy, $timezone));
    }

    /**
     * @param Collection<int, Appointment> $appointments
     * @param array<string, mixed> $metrics
     * @param array<string, string> $statusOptions
     * @return array<string, string>
     */
    private function files(Collection $appointments, array $metrics, array $statusOptions, string $periodLabel, Carbon $generatedAt, ?User $generatedBy, string $timezone): array
    {
        return [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->rootRelationshipsXml(),
            'docProps/app.xml' => $this->appPropertiesXml(),
            'docProps/core.xml' => $this->corePropertiesXml($generatedAt),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationshipsXml(),
            'xl/styles.xml' => $this->stylesXml(),
            'xl/worksheets/sheet1.xml' => $this->worksheetXml($appointments, $metrics, $statusOptions, $periodLabel, $generatedAt, $generatedBy, $timezone),
        ];
    }

    /** @param array<string, string> $files */
    private function zip(array $files): string
    {
        $localFiles = '';
        $centralDirectory = '';
        $offset = 0;
        [$dosTime, $dosDate] = $this->dosDateTime();

        foreach ($files as $name => $content) {
            $crc = (int) sprintf('%u', crc32($content));
            $size = strlen($content);
            $nameLength = strlen($name);

            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0).$name;
            $localFiles .= $localHeader.$content;

            $centralDirectory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset).$name;
            $offset += strlen($localHeader) + $size;
        }

        $entries = count($files);
        $centralSize = strlen($centralDirectory);
        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, $entries, $entries, $centralSize, $offset, 0);

        return $localFiles.$centralDirectory.$end;
    }

    /** @return array{0: int, 1: int} */
    private function dosDateTime(): array
    {
        $now = getdate();
        $time = ((int) $now['hours'] << 11) | ((int) $now['minutes'] << 5) | ((int) floor($now['seconds'] / 2));
        $date = (((int) $now['year'] - 1980) << 9) | ((int) $now['mon'] << 5) | (int) $now['mday'];

        return [$time, $date];
    }

    private function worksheetXml(Collection $appointments, array $metrics, array $statusOptions, string $periodLabel, Carbon $generatedAt, ?User $generatedBy, string $timezone): string
    {
        $rows = [];
        $rows[] = $this->row(1, [$this->inlineCell('A1', 'Reporte de citas', 1)]);
        $rows[] = $this->row(2, [$this->inlineCell('A2', 'Periodo: '.$periodLabel, 2)]);
        $rows[] = $this->row(3, [$this->inlineCell('A3', 'Generado: '.$generatedAt->timezone($timezone)->format('d/m/Y H:i'), 2)]);
        $rows[] = $this->row(4, [$this->inlineCell('A4', 'Usuario: '.($generatedBy?->name ?? 'Sistema'), 2)]);
        $rows[] = $this->row(6, [$this->inlineCell('A6', 'Resumen', 1)]);
        $rows[] = $this->row(7, [
            $this->inlineCell('A7', 'Total de citas', 3),
            $this->inlineCell('B7', 'Completadas', 3),
            $this->inlineCell('C7', 'Canceladas', 3),
            $this->inlineCell('D7', 'No asistió', 3),
            $this->inlineCell('E7', 'Tasa cancelación', 3),
        ]);
        $rows[] = $this->row(8, [
            $this->numberCell('A8', (int) ($metrics['total'] ?? 0), 4),
            $this->numberCell('B8', (int) ($metrics['completed'] ?? 0), 4),
            $this->numberCell('C8', (int) ($metrics['cancelled'] ?? 0), 4),
            $this->numberCell('D8', (int) ($metrics['noShow'] ?? 0), 4),
            $this->inlineCell('E8', ($metrics['cancellationRate'] ?? 0) . '%', 4),
        ]);

        $headers = ['Nro. cita', 'Fecha', 'Hora', 'Paciente', 'Médico', 'Servicio', 'Estado', 'Motivo'];
        $headerCells = [];
        foreach ($headers as $index => $header) {
            $headerCells[] = $this->inlineCell($this->cellRef($index + 1, 10), $header, 5);
        }
        $rows[] = $this->row(10, $headerCells);

        $rowNumber = 11;
        foreach ($appointments as $appointment) {
            $rows[] = $this->row($rowNumber, [
                $this->inlineCell('A'.$rowNumber, 'CIT-'.str_pad((string) $appointment->id, 6, '0', STR_PAD_LEFT), 7),
                $this->inlineCell('B'.$rowNumber, $appointment->appointment_date?->format('d/m/Y') ?? 'Sin fecha', 8),
                $this->inlineCell('C'.$rowNumber, substr((string)$appointment->start_time, 0, 5) ?? 'Sin hora', 8),
                $this->inlineCell('D'.$rowNumber, $appointment->patient?->full_name ?? 'Sin paciente', 6),
                $this->inlineCell('E'.$rowNumber, $appointment->doctor?->user?->name ?? 'Sin médico', 6),
                $this->inlineCell('F'.$rowNumber, $appointment->service?->name ?? 'Sin servicio', 6),
                $this->inlineCell('G'.$rowNumber, $statusOptions[$appointment->status] ?? $appointment->status, $this->statusStyleForStatus($appointment->status)),
                $this->inlineCell('H'.$rowNumber, $appointment->reason ?: 'Sin motivo', 6),
            ]);
            $rowNumber++;
        }

        $lastRow = max(10, $rowNumber - 1);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.
            '<sheetViews><sheetView workbookViewId="0"><pane ySplit="10" topLeftCell="A11" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A11" sqref="A11"/></sheetView></sheetViews>'.
            '<sheetFormatPr defaultRowHeight="18"/>'.
            '<cols><col min="1" max="1" width="16" customWidth="1"/><col min="2" max="2" width="14" customWidth="1"/><col min="3" max="3" width="12" customWidth="1"/><col min="4" max="4" width="30" customWidth="1"/><col min="5" max="5" width="28" customWidth="1"/><col min="6" max="6" width="30" customWidth="1"/><col min="7" max="7" width="18" customWidth="1"/><col min="8" max="8" width="40" customWidth="1"/></cols>'.
            '<sheetData>'.implode('', $rows).'</sheetData>'.
            '<autoFilter ref="A10:H'.$lastRow.'"/>'.
            '<mergeCells count="5"><mergeCell ref="A1:H1"/><mergeCell ref="A2:H2"/><mergeCell ref="A3:H3"/><mergeCell ref="A4:H4"/><mergeCell ref="A6:H6"/></mergeCells>'.
            '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'.
            '</worksheet>';
    }

    /** @param array<int, string> $cells */
    private function row(int $rowNumber, array $cells): string
    {
        return '<row r="'.$rowNumber.'">'.implode('', $cells).'</row>';
    }

    private function inlineCell(string $reference, string $value, int $style): string
    {
        return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t>'.$this->xml($value).'</t></is></c>';
    }

    private function numberCell(string $reference, int|float $value, int $style): string
    {
        return '<c r="'.$reference.'" s="'.$style.'"><v>'.str_replace(',', '.', (string) $value).'</v></c>';
    }

    private function cellRef(int $column, int $row): string
    {
        $letters = '';
        while ($column > 0) {
            $column--;
            $letters = chr(65 + ($column % 26)).$letters;
            $column = intdiv($column, 26);
        }

        return $letters.$row;
    }

    private function statusStyleForStatus(?string $status): int
    {
        return match ($status) {
            'confirmed', 'completed' => 15,
            'scheduled' => 16,
            'cancelled' => 17,
            'no_show' => 18,
            default => 10,
        };
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
            '<Default Extension="xml" ContentType="application/xml"/>'.
            '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'.
            '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'.
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'.
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'.
            '</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'.
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'.
            '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'.
            '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.
            '<sheets><sheet name="Reporte de citas" sheetId="1" r:id="rId1"/></sheets>'.
            '</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'.
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'.
            '</Relationships>';
    }

    private function appPropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'.
            '<Application>MediFlow</Application>'.
            '</Properties>';
    }

    private function corePropertiesXml(Carbon $generatedAt): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'.
            '<dc:creator>MediFlow</dc:creator>'.
            '<cp:lastModifiedBy>MediFlow</cp:lastModifiedBy>'.
            '<dcterms:created xsi:type="dcterms:W3CDTF">'.$generatedAt->copy()->utc()->toAtomString().'</dcterms:created>'.
            '<dcterms:modified xsi:type="dcterms:W3CDTF">'.$generatedAt->copy()->utc()->toAtomString().'</dcterms:modified>'.
            '</cp:coreProperties>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.
            '<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;$&quot;#,##0.00"/></numFmts>'.
            '<fonts count="8"><font><sz val="11"/><color rgb="FF0F172A"/><name val="Calibri"/></font><font><b/><sz val="16"/><color rgb="FF0F172A"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FF0F172A"/><name val="Calibri"/></font><font><b/><sz val="12"/><color rgb="FF047857"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FF047857"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFB45309"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFDC2626"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FF475569"/><name val="Calibri"/></font></fonts>'.
            '<fills count="7"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/><bgColor indexed="64"/></patternFill></fill></fills>'.
            '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right><top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border></borders>'.
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'.
            '<cellXfs count="19"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="164" fontId="4" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="164" fontId="5" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="164" fontId="6" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="164" fontId="7" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="0" fontId="4" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="5" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="6" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="7" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf></cellXfs>'.
            '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'.
            '</styleSheet>';
    }
}