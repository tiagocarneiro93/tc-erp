<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seeds countries (ISO 3166-1 alpha-2, Portuguese names) and units (a
 * starter set, technical-scope.md §6.4) — docs/plans/phase-1.md task 1.1.
 * Neither list is legally sensitive; no [VERIFY]. A future addition ships
 * as a new migration (CLAUDE.md — never edit a committed one).
 */
final class Version20260911205600 extends AbstractMigration
{
    private const COUNTRIES = [
        'PT' => 'Portugal',
        'ES' => 'Espanha',
        'FR' => 'França',
        'DE' => 'Alemanha',
        'IT' => 'Itália',
        'GB' => 'Reino Unido',
        'IE' => 'Irlanda',
        'NL' => 'Países Baixos',
        'BE' => 'Bélgica',
        'LU' => 'Luxemburgo',
        'CH' => 'Suíça',
        'AT' => 'Áustria',
        'DK' => 'Dinamarca',
        'SE' => 'Suécia',
        'NO' => 'Noruega',
        'FI' => 'Finlândia',
        'IS' => 'Islândia',
        'PL' => 'Polónia',
        'CZ' => 'Chéquia',
        'SK' => 'Eslováquia',
        'HU' => 'Hungria',
        'RO' => 'Roménia',
        'BG' => 'Bulgária',
        'GR' => 'Grécia',
        'HR' => 'Croácia',
        'SI' => 'Eslovénia',
        'EE' => 'Estónia',
        'LV' => 'Letónia',
        'LT' => 'Lituânia',
        'MT' => 'Malta',
        'CY' => 'Chipre',
        'AD' => 'Andorra',
        'MC' => 'Mónaco',
        'LI' => 'Liechtenstein',
        'SM' => 'São Marino',
        'VA' => 'Vaticano',
        'UA' => 'Ucrânia',
        'BY' => 'Bielorrússia',
        'MD' => 'Moldávia',
        'RS' => 'Sérvia',
        'ME' => 'Montenegro',
        'MK' => 'Macedónia do Norte',
        'AL' => 'Albânia',
        'BA' => 'Bósnia e Herzegovina',
        'XK' => 'Kosovo',
        'RU' => 'Rússia',
        'TR' => 'Turquia',
        'US' => 'Estados Unidos',
        'CA' => 'Canadá',
        'MX' => 'México',
        'BR' => 'Brasil',
        'AR' => 'Argentina',
        'CL' => 'Chile',
        'UY' => 'Uruguai',
        'PY' => 'Paraguai',
        'BO' => 'Bolívia',
        'PE' => 'Peru',
        'EC' => 'Equador',
        'CO' => 'Colômbia',
        'VE' => 'Venezuela',
        'GY' => 'Guiana',
        'SR' => 'Suriname',
        'PA' => 'Panamá',
        'CR' => 'Costa Rica',
        'NI' => 'Nicarágua',
        'HN' => 'Honduras',
        'SV' => 'El Salvador',
        'GT' => 'Guatemala',
        'BZ' => 'Belize',
        'CU' => 'Cuba',
        'JM' => 'Jamaica',
        'HT' => 'Haiti',
        'DO' => 'República Dominicana',
        'TT' => 'Trindade e Tobago',
        'BS' => 'Baamas',
        'BB' => 'Barbados',
        'CN' => 'China',
        'JP' => 'Japão',
        'KR' => 'Coreia do Sul',
        'KP' => 'Coreia do Norte',
        'IN' => 'Índia',
        'PK' => 'Paquistão',
        'BD' => 'Bangladeche',
        'LK' => 'Sri Lanka',
        'NP' => 'Nepal',
        'BT' => 'Butão',
        'MM' => 'Myanmar',
        'TH' => 'Tailândia',
        'VN' => 'Vietname',
        'LA' => 'Laos',
        'KH' => 'Camboja',
        'MY' => 'Malásia',
        'SG' => 'Singapura',
        'ID' => 'Indonésia',
        'PH' => 'Filipinas',
        'TL' => 'Timor-Leste',
        'MN' => 'Mongólia',
        'KZ' => 'Cazaquistão',
        'UZ' => 'Usbequistão',
        'TM' => 'Turquemenistão',
        'TJ' => 'Tajiquistão',
        'KG' => 'Quirguistão',
        'AF' => 'Afeganistão',
        'IR' => 'Irão',
        'IQ' => 'Iraque',
        'SY' => 'Síria',
        'LB' => 'Líbano',
        'JO' => 'Jordânia',
        'IL' => 'Israel',
        'PS' => 'Palestina',
        'SA' => 'Arábia Saudita',
        'YE' => 'Iémen',
        'OM' => 'Omã',
        'AE' => 'Emirados Árabes Unidos',
        'QA' => 'Catar',
        'BH' => 'Barém',
        'KW' => 'Kuwait',
        'GE' => 'Geórgia',
        'AM' => 'Arménia',
        'AZ' => 'Azerbaijão',
        'EG' => 'Egito',
        'LY' => 'Líbia',
        'TN' => 'Tunísia',
        'DZ' => 'Argélia',
        'MA' => 'Marrocos',
        'MR' => 'Mauritânia',
        'ML' => 'Mali',
        'NE' => 'Níger',
        'TD' => 'Chade',
        'SD' => 'Sudão',
        'SS' => 'Sudão do Sul',
        'ER' => 'Eritreia',
        'DJ' => 'Jibuti',
        'SO' => 'Somália',
        'ET' => 'Etiópia',
        'KE' => 'Quénia',
        'UG' => 'Uganda',
        'TZ' => 'Tanzânia',
        'RW' => 'Ruanda',
        'BI' => 'Burundi',
        'CD' => 'República Democrática do Congo',
        'CG' => 'República do Congo',
        'CM' => 'Camarões',
        'CF' => 'República Centro-Africana',
        'GA' => 'Gabão',
        'GQ' => 'Guiné Equatorial',
        'ST' => 'São Tomé e Príncipe',
        'CV' => 'Cabo Verde',
        'GW' => 'Guiné-Bissau',
        'GN' => 'Guiné',
        'SN' => 'Senegal',
        'GM' => 'Gâmbia',
        'SL' => 'Serra Leoa',
        'LR' => 'Libéria',
        'CI' => 'Costa do Marfim',
        'GH' => 'Gana',
        'TG' => 'Togo',
        'BJ' => 'Benim',
        'BF' => 'Burquina Faso',
        'NG' => 'Nigéria',
        'AO' => 'Angola',
        'MZ' => 'Moçambique',
        'ZM' => 'Zâmbia',
        'ZW' => 'Zimbabué',
        'MW' => 'Maláui',
        'NA' => 'Namíbia',
        'BW' => 'Botsuana',
        'ZA' => 'África do Sul',
        'LS' => 'Lesoto',
        'SZ' => 'Essuatíni',
        'MG' => 'Madagáscar',
        'MU' => 'Maurícia',
        'SC' => 'Seicheles',
        'KM' => 'Comores',
        'AU' => 'Austrália',
        'NZ' => 'Nova Zelândia',
        'FJ' => 'Fiji',
        'PG' => 'Papua-Nova Guiné',
        'SB' => 'Ilhas Salomão',
        'VU' => 'Vanuatu',
        'WS' => 'Samoa',
        'TO' => 'Tonga',
        'KI' => 'Quiribáti',
        'TV' => 'Tuvalu',
        'NR' => 'Nauru',
        'PW' => 'Palau',
        'FM' => 'Micronésia',
        'MH' => 'Ilhas Marshall',
    ];

    private const UNITS = [
        'UN' => ['Unidade', 0],
        'KG' => ['Quilograma', 3],
        'CX' => ['Caixa', 0],
        'L' => ['Litro', 3],
        'M' => ['Metro', 2],
        'M2' => ['Metro quadrado', 2],
        'M3' => ['Metro cúbico', 3],
        'DZ' => ['Dúzia', 0],
        'H' => ['Hora', 2],
    ];

    public function getDescription(): string
    {
        return 'Seed countries and units (docs/plans/phase-1.md task 1.1)';
    }

    public function up(Schema $schema): void
    {
        // ON CONFLICT DO NOTHING: a migration only ever runs once in
        // practice (Doctrine Migrations tracks applied versions), but the
        // seed itself stays safe to re-apply, same spirit as task 0.12's
        // SeedDemoDataCommand -- and it's what PHPUnit verifies below.
        foreach (self::COUNTRIES as $code => $name) {
            $this->addSql(
                'INSERT INTO countries (code, name) VALUES (:code, :name) ON CONFLICT (code) DO NOTHING',
                ['code' => $code, 'name' => $name],
            );
        }

        foreach (self::UNITS as $code => [$name, $decimals]) {
            $this->addSql(
                'INSERT INTO units (code, name, decimals) VALUES (:code, :name, :decimals) ON CONFLICT (code) DO NOTHING',
                ['code' => $code, 'name' => $name, 'decimals' => $decimals],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM units');
        $this->addSql('DELETE FROM countries');
    }
}
