<?php
declare(strict_types=1);

use OpenSpout\Reader\XLSX\Reader;

final class ScholarshipExcelImporter
{
    private const HEADERS = [
        'title' => 'Scholarship name',
        'provider_name' => 'Scholarship provider/organization',
        'provider_type' => 'Provider type',
        'website' => 'Official scholarship URL',
        'discovery_source' => 'Discovery source',
        'state_region' => 'State/region',
        'scope' => 'National vs. state/local',
        'eligible_grades' => 'Eligible grade levels',
        'school_level' => 'Middle school / high school / both',
        'category' => 'Scholarship category',
        'field_of_interest' => 'Field/area of interest',
        'award_amount' => 'Award amount',
        'number_of_awards' => 'Number of awards (if known)',
        'renewable' => 'Renewable (Yes/No/Unknown)',
        'maximum_value' => 'Maximum potential value',
        'open_date' => 'Application open date (if known)',
        'deadline' => 'Application deadline',
        'residency' => 'Residency requirement',
        'minimum_gpa' => 'Minimum GPA (if any)',
        'financial_need' => 'Financial need required (Yes/No/Unknown)',
        'essay' => 'Essay required',
        'recommendation' => 'Recommendation required',
        'transcript_test' => 'Transcript/test score required',
        'community_service' => 'Community service requirement',
        'leadership' => 'Leadership requirement',
        'other_requirements' => 'Other major eligibility requirements',
        'application_method' => 'Application method',
        'verification_date' => 'Verification date',
        'notes' => 'Notes',
    ];

    public static function read(string $path): array
    {
        if (!class_exists(Reader::class)) {
            throw new RuntimeException('Excel import support is missing. Run Composer install in the PHP application folder.');
        }
        if (!extension_loaded('zip')) {
            throw new RuntimeException('Excel import requires the PHP ZIP extension. Enable extension=zip in php.ini and restart Apache.');
        }

        $reader = new Reader();
        $reader->open($path);
        $scholarships = [];
        $row_errors = [];
        $row_number = 0;
        $data_row_count = 0;

        try {
            $sheets = $reader->getSheetIterator();
            if (!$sheets->valid()) {
                throw new RuntimeException('The Excel workbook does not contain a worksheet.');
            }

            foreach ($sheets as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $row_number++;
                    $values = array_map(
                        static fn($cell) => $cell->getValue(),
                        $row->getCells()
                    );

                    if ($row_number === 1) {
                        $indexes = [];
                        foreach ($values as $index => $heading) {
                            $normalized_heading = self::normalizeHeader(self::cellText($heading));
                            if ($normalized_heading !== '') {
                                if (isset($indexes[$normalized_heading])) {
                                    throw new RuntimeException('The Excel header row contains duplicate column names.');
                                }
                                $indexes[$normalized_heading] = $index;
                            }
                        }

                        $missing_headers = [];
                        foreach (self::HEADERS as $header) {
                            if (!isset($indexes[self::normalizeHeader($header)])) {
                                $missing_headers[] = $header;
                            }
                        }
                        if ($missing_headers !== []) {
                            throw new RuntimeException('This file does not match the scholarship template. Missing columns: ' . implode('; ', $missing_headers) . '.');
                        }
                        continue;
                    }

                    $source_data = [];
                    foreach (self::HEADERS as $key => $header) {
                        $index = $indexes[self::normalizeHeader($header)];
                        $source_data[$header] = self::cellText($values[$index] ?? null);
                    }
                    if (count(array_filter($source_data, static fn($value) => $value !== '')) === 0) {
                        continue;
                    }
                    if (++$data_row_count > 1000) {
                        throw new RuntimeException('A single upload may contain no more than 1,000 scholarships.');
                    }

                    $scholarship = [
                        'title' => $source_data[self::HEADERS['title']],
                        'provider_name' => $source_data[self::HEADERS['provider_name']],
                        'provider_type' => $source_data[self::HEADERS['provider_type']],
                        'website' => $source_data[self::HEADERS['website']],
                        'state_region' => $source_data[self::HEADERS['state_region']],
                        'scope' => $source_data[self::HEADERS['scope']],
                        'amount' => self::decimalAmount($source_data[self::HEADERS['award_amount']]),
                        'deadline' => self::sqlDate($values[$indexes[self::normalizeHeader(self::HEADERS['deadline'])]] ?? null),
                        'raw_data' => $source_data,
                    ];

                    $errors = [];
                    foreach (['title', 'provider_name', 'provider_type'] as $required_field) {
                        if ($scholarship[$required_field] === '') {
                            $errors[] = self::HEADERS[$required_field] . ' is required';
                        }
                    }
                    if (strlen($scholarship['title']) > 255) {
                        $errors[] = 'Scholarship name must be 255 characters or fewer';
                    }
                    if (strlen($scholarship['provider_name']) > 255) {
                        $errors[] = 'Scholarship provider/organization must be 255 characters or fewer';
                    }
                    if (strlen($scholarship['provider_type']) > 100) {
                        $errors[] = 'Provider type must be 100 characters or fewer';
                    }
                    if ($scholarship['website'] !== '' && (
                        strlen($scholarship['website']) > 255
                        || filter_var($scholarship['website'], FILTER_VALIDATE_URL) === false
                        || !in_array(strtolower((string) parse_url($scholarship['website'], PHP_URL_SCHEME)), ['http', 'https'], true)
                    )) {
                        $errors[] = 'Official scholarship URL must be a valid HTTP or HTTPS URL of 255 characters or fewer';
                    }

                    if ($errors !== []) {
                        if (count($row_errors) < 25) {
                            $row_errors[] = 'Excel row ' . $row_number . ': ' . implode('; ', $errors) . '.';
                        }
                        continue;
                    }

                    $description = [];
                    foreach (self::HEADERS as $key => $header) {
                        if (in_array($key, ['title', 'provider_name', 'provider_type', 'website'], true)) {
                            continue;
                        }
                        $value = $source_data[$header];
                        if ($value !== '') {
                            $description[] = $header . ': ' . $value;
                        }
                    }
                    $scholarship['description'] = implode("\n", $description);
                    $scholarships[] = $scholarship;
                }
                break;
            }
        } finally {
            $reader->close();
        }

        if ($row_number === 0) {
            throw new RuntimeException('The Excel workbook is empty.');
        }
        if ($row_errors !== []) {
            throw new RuntimeException(implode("\n", $row_errors));
        }
        if ($scholarships === []) {
            throw new RuntimeException('The Excel workbook contains no scholarship rows.');
        }

        return $scholarships;
    }

    public static function stateIdForRegion(string $region, string $scope, array $states): ?int
    {
        if (stripos($scope, 'national') !== false || stripos($region, 'national') === 0 || trim($region) === '') {
            return null;
        }

        foreach ($states as $state) {
            $state_name = (string) $state['state_name'];
            $state_code = (string) $state['state_code'];
            if (strcasecmp(trim($region), $state_code) === 0
                || strncasecmp(trim($region), $state_name, strlen($state_name)) === 0) {
                return (int) $state['state_id'];
            }
        }

        throw new RuntimeException('State/region "' . $region . '" does not match a state in the database.');
    }

    private static function normalizeHeader(string $header): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $header)));
    }

    private static function cellText(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        return trim((string) $value);
    }

    private static function sqlDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) $value));
        $date_errors = DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($date_errors !== false && ($date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0))
            || $date->format('Y-m-d') !== trim((string) $value)) {
            return null;
        }
        return $date->format('Y-m-d');
    }

    private static function decimalAmount(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^\$?\s*([\d,]+(?:\.\d{1,2})?)\s*$/', $value, $matches)) {
            return null;
        }
        $amount = str_replace(',', '', $matches[1]);
        if ((float) $amount > 9999999999.99) {
            return null;
        }
        return $amount;
    }
}