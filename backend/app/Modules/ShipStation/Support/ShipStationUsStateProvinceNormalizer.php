<?php

namespace App\Modules\ShipStation\Support;

use App\Modules\ShipStation\Exceptions\ShipStationApiException;

final class ShipStationUsStateProvinceNormalizer
{
    /**
     * @var array<string, string> lowercase name => abbreviation
     */
    private const NAME_TO_CODE = [
        'alabama' => 'AL',
        'alaska' => 'AK',
        'arizona' => 'AZ',
        'arkansas' => 'AR',
        'california' => 'CA',
        'colorado' => 'CO',
        'connecticut' => 'CT',
        'delaware' => 'DE',
        'florida' => 'FL',
        'georgia' => 'GA',
        'hawaii' => 'HI',
        'idaho' => 'ID',
        'illinois' => 'IL',
        'indiana' => 'IN',
        'iowa' => 'IA',
        'kansas' => 'KS',
        'kentucky' => 'KY',
        'louisiana' => 'LA',
        'maine' => 'ME',
        'maryland' => 'MD',
        'massachusetts' => 'MA',
        'michigan' => 'MI',
        'minnesota' => 'MN',
        'mississippi' => 'MS',
        'missouri' => 'MO',
        'montana' => 'MT',
        'nebraska' => 'NE',
        'nevada' => 'NV',
        'new hampshire' => 'NH',
        'new jersey' => 'NJ',
        'new mexico' => 'NM',
        'new york' => 'NY',
        'north carolina' => 'NC',
        'north dakota' => 'ND',
        'ohio' => 'OH',
        'oklahoma' => 'OK',
        'oregon' => 'OR',
        'pennsylvania' => 'PA',
        'rhode island' => 'RI',
        'south carolina' => 'SC',
        'south dakota' => 'SD',
        'tennessee' => 'TN',
        'texas' => 'TX',
        'utah' => 'UT',
        'vermont' => 'VT',
        'virginia' => 'VA',
        'washington' => 'WA',
        'west virginia' => 'WV',
        'wisconsin' => 'WI',
        'wyoming' => 'WY',
        'american samoa' => 'AS',
        'district of columbia' => 'DC',
        'federated states of micronesia' => 'FM',
        'guam' => 'GU',
        'marshall islands' => 'MH',
        'northern mariana islands' => 'MP',
        'palau' => 'PW',
        'puerto rico' => 'PR',
        'u.s. minor outlying islands' => 'UM',
        'us minor outlying islands' => 'UM',
        'virgin islands' => 'VI',
        'u.s. virgin islands' => 'VI',
        'us virgin islands' => 'VI',
    ];

    /**
     * @var list<string>
     */
    private const VALID_CODES = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA',
        'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK',
        'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY', 'AS', 'DC', 'FM', 'GU',
        'MH', 'MP', 'PW', 'PR', 'UM', 'VI',
    ];

    /**
     * @throws ShipStationApiException
     */
    public function normalizeForCountry(string $countryCode, ?string $stateProvince): ?string
    {
        if ($stateProvince === null || trim($stateProvince) === '') {
            return $stateProvince;
        }

        $country = strtoupper(trim($countryCode));

        if ($country !== 'US' && $country !== 'USA') {
            return trim($stateProvince);
        }

        return $this->normalizeUs(trim($stateProvince));
    }

    /**
     * @throws ShipStationApiException
     */
    private function normalizeUs(string $stateProvince): string
    {
        $upper = strtoupper($stateProvince);

        if (strlen($upper) === 2 && in_array($upper, self::VALID_CODES, true)) {
            return $upper;
        }

        $fromName = self::NAME_TO_CODE[strtolower($stateProvince)] ?? null;

        if ($fromName !== null) {
            return $fromName;
        }

        throw new ShipStationApiException(
            'Invalid US state/province: '.$stateProvince,
        );
    }
}
