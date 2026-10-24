<?php
declare(strict_types=1);

require_once __DIR__ . '/scholarship_row_cleaner.php';

function scholarship_list_values(string $value): array
{
    $values = preg_split('/[,;\r\n]+/', clean_import_text($value)) ?: [];
    $values = array_map('clean_import_text', $values);
    return array_values(array_unique(array_filter($values, static fn(string $item): bool => $item !== '')));
}

function scholarship_catalog_id(PDO $pdo, string $table, string $idColumn, string $nameColumn, string $name): int
{
    $allowed = [
        'grade_levels' => ['grade_level_id', 'grade_name'],
        'categories' => ['category_id', 'category_name'],
        'fields_of_study' => ['field_id', 'field_name'],
    ];
    if (!isset($allowed[$table]) || $allowed[$table] !== [$idColumn, $nameColumn]) {
        throw new InvalidArgumentException('Unsupported scholarship catalog.');
    }

    $find = $pdo->prepare("SELECT {$idColumn} FROM {$table} WHERE {$nameColumn} = ? LIMIT 1");
    $find->execute([$name]);
    $id = $find->fetchColumn();
    if ($id !== false) {
        return (int)$id;
    }

    $insert = $pdo->prepare("INSERT INTO {$table} ({$nameColumn}) VALUES (?)");
    $insert->execute([$name]);
    return (int)$pdo->lastInsertId();
}

function persist_scholarship(PDO $pdo, array $cleaned, ?int $scholarshipId = null, array $validationMessages = []): int
{
    $findType = $pdo->prepare('SELECT provider_type_id FROM provider_type WHERE provider_type_name = ? LIMIT 1');
    $findType->execute([$cleaned['provider_type']]);
    $typeId = $findType->fetchColumn();
    if ($typeId === false) {
        $addType = $pdo->prepare('INSERT INTO provider_type (provider_type_name) VALUES (?)');
        $addType->execute([$cleaned['provider_type']]);
        $typeId = (int)$pdo->lastInsertId();
    }

    $findProvider = $pdo->prepare(
        'SELECT provider_id FROM providers WHERE provider_name = ? AND provider_type_id = ? LIMIT 1'
    );
    $findProvider->execute([$cleaned['provider_name'], (int)$typeId]);
    $providerId = $findProvider->fetchColumn();
    if ($providerId === false) {
        $addProvider = $pdo->prepare(
            'INSERT INTO providers (provider_type_id, provider_name, website) VALUES (?, ?, ?)'
        );
        $addProvider->execute([
            (int)$typeId,
            $cleaned['provider_name'],
            $cleaned['official_url'] !== '' && strlen($cleaned['official_url']) <= 255
                ? $cleaned['official_url']
                : null,
        ]);
        $providerId = (int)$pdo->lastInsertId();
    } else {
        $providerId = (int)$providerId;
    }

    $description = json_encode($cleaned, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $validationJson = json_encode(
        array_values(array_unique($validationMessages)),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    $values = [
        'provider_id' => $providerId,
        'state_id' => !empty($cleaned['state_id']) ? (int)$cleaned['state_id'] : null,
        'title' => mb_substr((string)$cleaned['name'], 0, 255),
        'description' => $description,
        'amount' => $cleaned['amount'] ?? null,
        'deadline' => $cleaned['deadline'] ?? null,
        'official_url' => $cleaned['official_url'] !== '' ? $cleaned['official_url'] : null,
        'scope' => $cleaned['scope'] ?? 'State/Local',
        'is_national' => (int)($cleaned['is_national'] ?? 0),
        'open_date' => $cleaned['open_date'] ?? null,
        'verification_date' => $cleaned['verification_date'] ?? null,
        'validation_messages' => $validationJson,
    ];

    if ($scholarshipId === null) {
        $insert = $pdo->prepare(
            'INSERT INTO scholarships
                (provider_id, state_id, title, description, amount, deadline, official_url,
                 scope, is_national, open_date, verification_date, validation_messages)
             VALUES
                (:provider_id, :state_id, :title, :description, :amount, :deadline, :official_url,
                 :scope, :is_national, :open_date, :verification_date, :validation_messages)'
        );
        $insert->execute($values);
        $scholarshipId = (int)$pdo->lastInsertId();
    } else {
        $values['scholarship_id'] = $scholarshipId;
        $update = $pdo->prepare(
            'UPDATE scholarships SET
                provider_id = :provider_id, state_id = :state_id, title = :title,
                description = :description, amount = :amount, deadline = :deadline,
                official_url = :official_url, scope = :scope, is_national = :is_national,
                open_date = :open_date, verification_date = :verification_date,
                validation_messages = :validation_messages
             WHERE scholarship_id = :scholarship_id'
        );
        $update->execute($values);
        if ($update->rowCount() === 0) {
            $exists = $pdo->prepare('SELECT 1 FROM scholarships WHERE scholarship_id = ?');
            $exists->execute([$scholarshipId]);
            if (!$exists->fetchColumn()) {
                throw new RuntimeException('The scholarship being updated no longer exists.');
            }
        }
    }

    $pdo->prepare('DELETE FROM scholarship_grades WHERE scholarship_id = ?')->execute([$scholarshipId]);
    $pdo->prepare('DELETE FROM scholarship_categories WHERE scholarship_id = ?')->execute([$scholarshipId]);
    $pdo->prepare('DELETE FROM scholarship_fields WHERE scholarship_id = ?')->execute([$scholarshipId]);

    $gradeInsert = $pdo->prepare(
        'INSERT INTO scholarship_grades (scholarship_id, grade_level_id) VALUES (?, ?)'
    );
    foreach (array_unique($cleaned['grade_ids'] ?? []) as $grade) {
        $gradeId = scholarship_catalog_id(
            $pdo,
            'grade_levels',
            'grade_level_id',
            'grade_name',
            'Grade ' . (int)$grade
        );
        $gradeInsert->execute([$scholarshipId, $gradeId]);
    }

    $categoryInsert = $pdo->prepare(
        'INSERT INTO scholarship_categories (scholarship_id, category_id) VALUES (?, ?)'
    );
    foreach (scholarship_list_values((string)($cleaned['category'] ?? '')) as $category) {
        $categoryId = scholarship_catalog_id($pdo, 'categories', 'category_id', 'category_name', $category);
        $categoryInsert->execute([$scholarshipId, $categoryId]);
    }

    $fieldInsert = $pdo->prepare(
        'INSERT INTO scholarship_fields (scholarship_id, field_id) VALUES (?, ?)'
    );
    foreach (scholarship_list_values((string)($cleaned['field_of_interest'] ?? '')) as $field) {
        $fieldId = scholarship_catalog_id($pdo, 'fields_of_study', 'field_id', 'field_name', $field);
        $fieldInsert->execute([$scholarshipId, $fieldId]);
    }

    return $scholarshipId;
}
