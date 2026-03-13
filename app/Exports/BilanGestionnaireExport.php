<?php
// app/Exports/BilanGestionnaireExport.php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class BilanGestionnaireExport implements FromArray, WithHeadings, WithTitle, WithStyles
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return [
            ['Wilaya', $this->data['wilaya_nom'] . ' (' . $this->data['wilaya_id'] . ')'],
            ['Période', $this->data['periode']['libelle']],
            ['Date de génération', $this->data['date_generation']],
            [],
            ['STATISTIQUES DES COLIS'],
            ['Total colis', $this->data['colis']['total']],
            ['Valeur totale', number_format($this->data['colis']['valeur_totale'], 0, ',', ' ') . ' DA'],
            ['Poids total', $this->data['colis']['poids_total'] . ' kg'],
            [],
            ['STATISTIQUES DES LIVRAISONS'],
            ['Total livraisons', $this->data['livraisons']['total']],
            ['Terminées', $this->data['livraisons']['terminees']],
            ['Revenus livraisons', number_format($this->data['livraisons']['prix_total'], 0, ',', ' ') . ' DA'],
            [],
            ['STATISTIQUES DES NAVETTES'],
            ['Total navettes', $this->data['navettes']['total']],
            ['Revenus navettes', number_format($this->data['navettes']['revenus'], 0, ',', ' ') . ' DA'],
            [],
            ['BILAN FINANCIER'],
            ['Valeur des colis', number_format($this->data['finances']['valeur_colis'], 0, ',', ' ') . ' DA'],
            ['Revenus livraisons', number_format($this->data['finances']['revenus_livraisons'], 0, ',', ' ') . ' DA'],
            ['Revenus navettes', number_format($this->data['finances']['revenus_navettes'], 0, ',', ' ') . ' DA'],
            ['CHIFFRE D\'AFFAIRES TOTAL', number_format($this->data['finances']['chiffre_affaires_total'], 0, ',', ' ') . ' DA'],
        ];
    }

    public function headings(): array
    {
        return [
            ['BILAN FINANCIER - ' . $this->data['wilaya_nom']],
        ];
    }

    public function title(): string
    {
        return 'Bilan ' . $this->data['wilaya_id'];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('A5')->getFont()->setBold(true);
        $sheet->getStyle('A10')->getFont()->setBold(true);
        $sheet->getStyle('A15')->getFont()->setBold(true);
        $sheet->getStyle('A19')->getFont()->setBold(true);

        $sheet->getStyle('A22:B22')->getFont()->setBold(true);
        $sheet->getStyle('A22:B22')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE8F0FE');

        return [];
    }
}
