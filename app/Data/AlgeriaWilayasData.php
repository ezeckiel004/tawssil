<?php

namespace App\Data;

class AlgeriaWilayasData
{
    public static function getWilayas()
    {
        return [
            '16' => [
                'name' => 'Alger',
                'communes' => [
                    'Alger Centre',
                    'Bab El Oued',
                    'Bab Ezzouar',
                    'Bainem',
                    'Baldia',
                    'Baraki',
                    'Ben Aknoun',
                    'Bentalha',
                    'Béni Messous',
                    'Bir Khemis',
                    'Bir Mourad Raïs',
                    'Blida',
                    'Bourouba',
                    'Bouzaréah',
                    'Chéraga',
                    'Cheikh El Bachir',
                    'Chéraga',
                    'Dar El Beida',
                    'Dar Es Salaam',
                    'Doura',
                    'El Achour',
                    'El Biar',
                    'El Harrach',
                    'El Madania',
                    'El Mouradia',
                    'El Oued',
                    'Sidi Fredj',
                ]
            ],
            '9' => [
                'name' => 'Blida',
                'communes' => [
                    'Blida',
                    'Bouarfa',
                    'Bouguara',
                    'Boughara',
                    'Soumaa',
                    'Bou Saada',
                    'Chréa',
                    'Djurdjura',
                    'Larbaa',
                    'Lakhdaria',
                    'Mérouane',
                    'Mouzaïa',
                    'Ouaguenoun',
                    'Ouled Aïch',
                    'Ouled Yaïch',
                    'Saoula',
                    'Sidi Ghiles',
                    'Sidi Jedidi',
                ]
            ],
            '35' => [
                'name' => 'Boumerdès',
                'communes' => [
                    'Boumerdès',
                    'Amraoua',
                    'Baghlia',
                    'Beni Amrane',
                    'Beni Haoua',
                    'Bhalil',
                    'Bou Ismail',
                    'Chlef',
                    'Corso',
                    'Darouala',
                    'Dellys',
                    'Djinet',
                    'Larbaa',
                    'Naciria',
                    'Ouled Aïch',
                    'Rouïba',
                    'Tala Ifacene',
                    'Tassrift',
                    'Thenia',
                    'Tidjelley',
                    'Timezrit',
                ]
            ],
            '42' => [
                'name' => 'Tipaza',
                'communes' => [
                    'Tipaza',
                    'Ahmer El Ain',
                    'Ain Romana',
                    'Bou Ismail',
                    'Castiglione',
                    'Chenoua',
                    'Fouka',
                    'Gouraya',
                    'Hadjout',
                    'Hadjout',
                    'Koléa',
                    'Menaceur',
                    'Nador',
                    'Ténès',
                    'Tiddis',
                ]
            ],
            '10' => [
                'name' => 'Bouira',
                'communes' => [
                    'Bouira',
                    'Aghbalou',
                    'Aïn Bessem',
                    'Aïn Turk',
                    'Akbou',
                    'Assi Youcef',
                    'Boghni',
                    'Daïra',
                    'Djimla',
                    'Draâ Ben Khedda',
                    'Kais',
                    'Kadiria',
                    'Kimis',
                    'Lafha',
                    'Lakhdaria',
                    'M\'Chedallah',
                    'Mahfoudh',
                    'Meghila',
                    'Oullis',
                    'Saroual',
                    'Somaâ',
                    'Tacheta',
                    'Taddés',
                ]
            ],
            '15' => [
                'name' => 'Tizi Ouzou',
                'communes' => [
                    'Tizi Ouzou',
                    'Aït Khaled',
                    'Aït Yenni',
                    'Akarkar',
                    'Akbil',
                    'Azazga',
                    'Azazga',
                    'Beni Ounif',
                    'Bou Nouh',
                    'Boumaguène',
                    'Draâ El Mizan',
                    'Draa El Mizan',
                    'Iferhounène',
                    'Ilmaten',
                    'Imsouhal',
                    'Irdjen',
                    'Larbaa Nath Iraten',
                    'Mechtras',
                    'Mekla',
                    'Mena',
                    'Mesbah',
                    'Ouacif',
                    'Ouagni',
                    'Ouadhias',
                    'Ourida',
                    'Takerkoust',
                    'Tamarot',
                    'Tamridjet',
                    'Tanayas',
                    'Taourirt Mimoun',
                    'Tassaft',
                    'Tazmalt',
                    'Tizi Gheniff',
                    'Tizi N\'Tleta',
                    'Timizart',
                    'Trighet',
                    'Yakourène',
                    'Zaouatene',
                ]
            ],
        ];
    }

    public static function getCommunesByWilaya($wilayaCode)
    {
        $wilayas = self::getWilayas();
        return $wilayas[$wilayaCode]['communes'] ?? [];
    }

    public static function getWilayaCode($wilayaName)
    {
        $wilayas = self::getWilayas();
        foreach ($wilayas as $code => $wilaya) {
            if (strtolower($wilaya['name']) === strtolower($wilayaName)) {
                return $code;
            }
        }
        return null;
    }
}
