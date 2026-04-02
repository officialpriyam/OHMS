<?php
/**
 * OHMS.
 *
 * @copyright OHMS, Inc (https://www.OHMS.org)
 * @license   Apache-2.0
 *
 * Copyright OHMS, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 *
 * ---
 *
 * BoxBilling.
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */



namespace Priyx\Mod\Product;

use Priyx\InjectionAwareInterface;

class ConfigurableOptionsManager implements InjectionAwareInterface
{
    public const TYPE_DROPDOWN = 'dropdown';
    public const TYPE_RADIO = 'radio';
    public const TYPE_YES_NO = 'yes_no';
    public const TYPE_QUANTITY = 'quantity';

    protected $di;
    protected bool $schemaInitialized = false;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function ensureSchema(): void
    {
        if ($this->schemaInitialized) {
            return;
        }

        $queries = [
            "CREATE TABLE IF NOT EXISTS `product_configurable_group` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL,
                `description` text DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
            "CREATE TABLE IF NOT EXISTS `product_configurable_option` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `product_configurable_group_id` int(11) unsigned NOT NULL,
                `title` varchar(255) NOT NULL,
                `description` text DEFAULT NULL,
                `type` varchar(32) NOT NULL,
                `required` tinyint(1) NOT NULL DEFAULT 0,
                `hidden` tinyint(1) NOT NULL DEFAULT 0,
                `sort_order` int(11) NOT NULL DEFAULT 0,
                `pricing` text DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_product_configurable_group` (`product_configurable_group_id`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
            "CREATE TABLE IF NOT EXISTS `product_configurable_option_value` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `product_configurable_option_id` int(11) unsigned NOT NULL,
                `label` varchar(255) NOT NULL,
                `hidden` tinyint(1) NOT NULL DEFAULT 0,
                `sort_order` int(11) NOT NULL DEFAULT 0,
                `pricing` text DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_product_configurable_option_value` (`product_configurable_option_id`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
            "CREATE TABLE IF NOT EXISTS `product_configurable_option_assignment` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `product_id` int(11) unsigned NOT NULL,
                `product_configurable_group_id` int(11) unsigned NOT NULL,
                `sort_order` int(11) NOT NULL DEFAULT 0,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_product_configurable_option_assignment` (`product_id`, `product_configurable_group_id`),
                KEY `idx_product_configurable_option_assignment_group` (`product_configurable_group_id`, `sort_order`),
                KEY `idx_product_configurable_option_assignment_product` (`product_id`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        ];

        foreach ($queries as $query) {
            $this->di['db']->exec($query);
        }

        $this->schemaInitialized = true;
    }

    public function getOptionTypes(): array
    {
        return [
            self::TYPE_DROPDOWN => 'Dropdown',
            self::TYPE_RADIO => 'Radio',
            self::TYPE_YES_NO => 'Yes/No',
            self::TYPE_QUANTITY => 'Quantity',
        ];
    }

    public function getPricingPeriods(): array
    {
        return [
            'once' => 'One time',
            '1W' => 'Weekly',
            '1M' => 'Monthly',
            '3M' => 'Quarterly',
            '6M' => 'Semi-Annual',
            '1Y' => 'Annual',
            '2Y' => 'Biennial',
            '3Y' => 'Triennial',
        ];
    }

    public function getGroupsApiArray(bool $deep = true, bool $admin = true): array
    {
        try {
            $groups = $this->di['db']->find('ProductConfigOptionGroup', ' ORDER BY id ASC');
        } catch (\Throwable $e) {
            return [];
        }
        $result = [];

        foreach ($groups as $group) {
            $result[] = $this->toGroupApiArray($group, $deep, $admin);
        }

        return $result;
    }

    public function toGroupApiArray(\Model_ProductConfigOptionGroup $group, bool $deep = true, bool $admin = true): array
    {
        $data = $this->di['db']->toArray($group);
        $data['products'] = [];
        $data['product_ids'] = [];

        try {
            $assignments = $this->di['db']->find(
                'ProductConfigOptionAssignment',
                'product_config_option_group_id = :group_id ORDER BY sort_order ASC, id ASC',
                [':group_id' => $group->id]
            );
        } catch (\Throwable $e) {
            $assignments = [];
        }

        foreach ($assignments as $assignment) {
            $product = $this->di['db']->load('Product', $assignment->product_id);
            if (!$product instanceof \Model_Product || $product->is_addon) {
                continue;
            }

            $data['products'][] = [
                'id' => $product->id,
                'title' => $product->title,
            ];
            $data['product_ids'][] = (int) $product->id;
        }

        if ($deep) {
            $options = $this->findGroupOptions((int) $group->id, $admin);
            $data['options'] = [];
            foreach ($options as $option) {
                $data['options'][] = $this->toOptionApiArray($option, true, $admin);
            }
        } else {
            $data['options'] = [];
        }

        return $data;
    }

    public function getProductGroupsApiArray(\Model_Product $product, bool $deep = true, bool $admin = false): array
    {
        try {
            $assignments = $this->di['db']->find(
                'ProductConfigOptionAssignment',
                'product_id = :product_id ORDER BY sort_order ASC, id ASC',
                [':product_id' => $product->id]
            );
        } catch (\Throwable $e) {
            return [];
        }

        $result = [];
        foreach ($assignments as $assignment) {
            $group = $this->di['db']->load('ProductConfigOptionGroup', $assignment->product_config_option_group_id);
            if (!$group instanceof \Model_ProductConfigOptionGroup) {
                continue;
            }

            $result[] = $this->toGroupApiArray($group, $deep, $admin);
        }

        return $result;
    }

    public function getAssignedGroupIds(\Model_Product $product): array
    {
        try {
            $assignments = $this->di['db']->find(
                'ProductConfigOptionAssignment',
                'product_id = :product_id ORDER BY sort_order ASC, id ASC',
                [':product_id' => $product->id]
            );
        } catch (\Throwable $e) {
            return [];
        }

        $result = [];
        foreach ($assignments as $assignment) {
            $result[] = (int) $assignment->product_config_option_group_id;
        }

        return $result;
    }

    public function createGroup(array $data): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \Priyx_Exception('Configurable option group title is required');
        }

        $group = $this->di['db']->dispense('ProductConfigOptionGroup');
        $group->title = $title;
        $group->description = trim((string) ($data['description'] ?? ''));
        $group->created_at = date('Y-m-d H:i:s');
        $group->updated_at = date('Y-m-d H:i:s');
        $id = (int) $this->di['db']->store($group);

        if (isset($data['product_ids']) && is_array($data['product_ids'])) {
            $group = $this->di['db']->getExistingModelById('ProductConfigOptionGroup', $id);
            $this->syncGroupProducts($group, $data['product_ids']);
        }

        $this->di['logger']->info('Created configurable option group #%s', $id);

        return $id;
    }

    public function updateGroup(\Model_ProductConfigOptionGroup $group, array $data): bool
    {
        $title = trim((string) ($data['title'] ?? $group->title));
        if ($title === '') {
            throw new \Priyx_Exception('Configurable option group title is required');
        }

        $group->title = $title;
        $group->description = trim((string) ($data['description'] ?? $group->description));
        $group->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($group);

        if (array_key_exists('product_ids', $data)) {
            $productIds = is_array($data['product_ids']) ? $data['product_ids'] : [];
            $this->syncGroupProducts($group, $productIds);
        }

        $this->di['logger']->info('Updated configurable option group #%s', $group->id);

        return true;
    }

    public function deleteGroup(\Model_ProductConfigOptionGroup $group): bool
    {
        try {
            $options = $this->di['db']->find(
                'ProductConfigOption',
                'product_config_option_group_id = :group_id',
                [':group_id' => $group->id]
            );
        } catch (\Throwable $e) {
            $options = [];
        }

        foreach ($options as $option) {
            $this->deleteOption($option);
        }

        try {
            $assignments = $this->di['db']->find(
                'ProductConfigOptionAssignment',
                'product_config_option_group_id = :group_id',
                [':group_id' => $group->id]
            );
        } catch (\Throwable $e) {
            $assignments = [];
        }
        foreach ($assignments as $assignment) {
            $this->di['db']->trash($assignment);
        }

        $id = $group->id;
        $this->di['db']->trash($group);
        $this->di['logger']->info('Deleted configurable option group #%s', $id);

        return true;
    }

    public function createOption(\Model_ProductConfigOptionGroup $group, array $data): int
    {
        $option = $this->di['db']->dispense('ProductConfigOption');
        $option->product_config_option_group_id = $group->id;
        $option->created_at = date('Y-m-d H:i:s');

        $this->populateOption($option, $data);
        $id = (int) $this->di['db']->store($option);
        $option = $this->di['db']->getExistingModelById('ProductConfigOption', $id);
        $this->syncOptionValues($option, $data['values'] ?? []);

        $this->di['logger']->info('Created configurable option #%s in group #%s', $id, $group->id);

        return $id;
    }

    public function updateOption(\Model_ProductConfigOption $option, array $data): bool
    {
        $this->populateOption($option, $data);
        $this->di['db']->store($option);
        $this->syncOptionValues($option, $data['values'] ?? []);

        $this->di['logger']->info('Updated configurable option #%s', $option->id);

        return true;
    }

    public function deleteOption(\Model_ProductConfigOption $option): bool
    {
        try {
            $values = $this->di['db']->find(
                'ProductConfigOptionValue',
                'product_config_option_id = :option_id',
                [':option_id' => $option->id]
            );
        } catch (\Throwable $e) {
            $values = [];
        }

        foreach ($values as $value) {
            $this->di['db']->trash($value);
        }

        $id = $option->id;
        $this->di['db']->trash($option);
        $this->di['logger']->info('Deleted configurable option #%s', $id);

        return true;
    }

    public function toOptionApiArray(\Model_ProductConfigOption $option, bool $deep = true, bool $admin = true): array
    {
        $data = $this->di['db']->toArray($option);
        $data['required'] = (bool) $option->required;
        $data['hidden'] = (bool) $option->hidden;
        $data['sort_order'] = (int) $option->sort_order;
        $data['pricing'] = $this->decodePricing($option->pricing);
        $data['values'] = [];

        if ($deep && in_array($option->type, [self::TYPE_DROPDOWN, self::TYPE_RADIO], true)) {
            $values = $this->findOptionValues((int) $option->id, $admin);
            foreach ($values as $value) {
                $data['values'][] = $this->toValueApiArray($value);
            }
        }

        return $data;
    }

    public function toValueApiArray(\Model_ProductConfigOptionValue $value): array
    {
        $data = $this->di['db']->toArray($value);
        $data['hidden'] = (bool) $value->hidden;
        $data['sort_order'] = (int) $value->sort_order;
        $data['pricing'] = $this->decodePricing($value->pricing);

        return $data;
    }

    public function syncProductGroups(\Model_Product $product, array $groupIds): bool
    {
        $cleanIds = $this->sanitizeIds($groupIds);
        try {
            $existing = $this->di['db']->find(
                'ProductConfigOptionAssignment',
                'product_id = :product_id',
                [':product_id' => $product->id]
            );
        } catch (\Throwable $e) {
            $existing = [];
        }

        $byGroup = [];
        foreach ($existing as $assignment) {
            $byGroup[(int) $assignment->product_config_option_group_id] = $assignment;
        }

        foreach ($cleanIds as $sortOrder => $groupId) {
            if (isset($byGroup[$groupId])) {
                $assignment = $byGroup[$groupId];
                unset($byGroup[$groupId]);
            } else {
                $assignment = $this->di['db']->dispense('ProductConfigOptionAssignment');
                $assignment->product_id = $product->id;
                $assignment->product_config_option_group_id = $groupId;
                $assignment->created_at = date('Y-m-d H:i:s');
            }

            $assignment->sort_order = $sortOrder;
            $assignment->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($assignment);
        }

        foreach ($byGroup as $assignment) {
            $this->di['db']->trash($assignment);
        }

        return true;
    }

    public function getSelectionSummary(\Model_Product $product, ?array $config = null, bool $validate = false): array
    {
        $config ??= [];
        $groups = $this->getProductGroupsApiArray($product, true, true);
        $selected = $config['config_options'] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }

        $period = $config['period'] ?? null;
        $items = [];
        $total = 0.0;

        foreach ($groups as $group) {
            foreach (($group['options'] ?? []) as $option) {
                if (!empty($option['hidden'])) {
                    continue;
                }

                $selection = $this->buildSelectedOptionRow($product, $group, $option, $selected, $period, $validate);
                if ($selection === null) {
                    continue;
                }

                $items[] = $selection;
                $total += $selection['price'];
            }
        }

        return [
            'items' => $items,
            'price' => round($total, 2),
        ];
    }

    public function validateSelections(\Model_Product $product, array $config): bool
    {
        $this->getSelectionSummary($product, $config, true);

        return true;
    }

    public function getMinimumRequiredPrice(\Model_Product $product, ?string $period = null): float
    {
        $groups = $this->getProductGroupsApiArray($product, true, true);
        $total = 0.0;

        foreach ($groups as $group) {
            foreach (($group['options'] ?? []) as $option) {
                if (!empty($option['hidden']) || empty($option['required'])) {
                    continue;
                }

                switch ($option['type']) {
                    case self::TYPE_DROPDOWN:
                    case self::TYPE_RADIO:
                        $prices = [];
                        foreach (($option['values'] ?? []) as $value) {
                            if (!empty($value['hidden'])) {
                                continue;
                            }
                            $prices[] = $this->getConfiguredPriceAmount($product, $value['pricing'] ?? [], $period);
                        }
                        if (!empty($prices)) {
                            $total += min($prices);
                        }
                        break;

                    case self::TYPE_YES_NO:
                    case self::TYPE_QUANTITY:
                        $total += $this->getConfiguredPriceAmount($product, $option['pricing'] ?? [], $period);
                        break;
                }
            }
        }

        return round($total, 2);
    }

    public function getConfiguredPriceAmount(\Model_Product $product, array $pricing, ?string $period = null): float
    {
        $priceType = $this->getProductPriceType($product);
        if ($priceType === 'recurrent') {
            if ($period && array_key_exists($period, $pricing)) {
                return (float) $pricing[$period];
            }

            return 0.0;
        }

        return (float) ($pricing['once'] ?? 0);
    }

    protected function populateOption(\Model_ProductConfigOption $option, array $data): void
    {
        $title = trim((string) ($data['title'] ?? $option->title));
        if ($title === '') {
            throw new \Priyx_Exception('Configurable option name is required');
        }

        $type = $data['type'] ?? $option->type;
        if (!array_key_exists($type, $this->getOptionTypes())) {
            throw new \Priyx_Exception('Unknown configurable option type');
        }

        $option->title = $title;
        $option->description = trim((string) ($data['description'] ?? $option->description));
        $option->type = $type;
        $option->required = (int) !empty($data['required']);
        $option->hidden = (int) !empty($data['hidden']);
        $option->sort_order = (int) ($data['sort_order'] ?? $option->sort_order ?? 0);
        $option->pricing = json_encode(
            in_array($type, [self::TYPE_YES_NO, self::TYPE_QUANTITY], true) ? $this->normalizePricing($data['pricing'] ?? []) : []
        );
        $option->updated_at = date('Y-m-d H:i:s');
    }

    protected function syncOptionValues(\Model_ProductConfigOption $option, $rows): void
    {
        $type = $option->type;
        try {
            $existing = $this->di['db']->find(
                'ProductConfigOptionValue',
                'product_config_option_id = :option_id',
                [':option_id' => $option->id]
            );
        } catch (\Throwable $e) {
            $existing = [];
        }

        if (!in_array($type, [self::TYPE_DROPDOWN, self::TYPE_RADIO], true)) {
            foreach ($existing as $value) {
                $this->di['db']->trash($value);
            }

            return;
        }

        $rows = is_array($rows) ? $rows : [];
        $keepIds = [];
        $existingById = [];
        foreach ($existing as $value) {
            $existingById[(int) $value->id] = $value;
        }

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $valueId = (int) ($row['id'] ?? 0);
            if ($valueId && isset($existingById[$valueId])) {
                $value = $existingById[$valueId];
            } else {
                $value = $this->di['db']->dispense('ProductConfigOptionValue');
                $value->product_config_option_id = $option->id;
                $value->created_at = date('Y-m-d H:i:s');
            }

            $value->label = $label;
            $value->sort_order = (int) ($row['sort_order'] ?? $index);
            $value->hidden = (int) !empty($row['hidden']);
            $value->pricing = json_encode($this->normalizePricing($row['pricing'] ?? []));
            $value->updated_at = date('Y-m-d H:i:s');
            $storedId = (int) $this->di['db']->store($value);
            $keepIds[] = $storedId;
        }

        foreach ($existing as $value) {
            if (!in_array((int) $value->id, $keepIds, true)) {
                $this->di['db']->trash($value);
            }
        }
    }

    protected function findGroupOptions(int $groupId, bool $admin = false): array
    {
        $sql = 'product_config_option_group_id = :group_id';
        if (!$admin) {
            $sql .= ' AND hidden = 0';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        try {
            return $this->di['db']->find('ProductConfigOption', $sql, [':group_id' => $groupId]);
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function findOptionValues(int $optionId, bool $admin = false): array
    {
        $sql = 'product_config_option_id = :option_id';
        if (!$admin) {
            $sql .= ' AND hidden = 0';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        try {
            return $this->di['db']->find('ProductConfigOptionValue', $sql, [':option_id' => $optionId]);
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function syncGroupProducts(\Model_ProductConfigOptionGroup $group, array $productIds): bool
    {
        $cleanIds = $this->sanitizeIds($productIds);
        try {
            $existing = $this->di['db']->find(
                'ProductConfigOptionAssignment',
                'product_config_option_group_id = :group_id',
                [':group_id' => $group->id]
            );
        } catch (\Throwable $e) {
            $existing = [];
        }

        $byProduct = [];
        foreach ($existing as $assignment) {
            $byProduct[(int) $assignment->product_id] = $assignment;
        }

        foreach ($cleanIds as $sortOrder => $productId) {
            $product = $this->di['db']->load('Product', $productId);
            if (!$product instanceof \Model_Product || $product->is_addon) {
                continue;
            }

            if (isset($byProduct[$productId])) {
                $assignment = $byProduct[$productId];
                unset($byProduct[$productId]);
            } else {
                $assignment = $this->di['db']->dispense('ProductConfigOptionAssignment');
                $assignment->product_id = $productId;
                $assignment->product_config_option_group_id = $group->id;
                $assignment->created_at = date('Y-m-d H:i:s');
            }

            $assignment->sort_order = $sortOrder;
            $assignment->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($assignment);
        }

        foreach ($byProduct as $assignment) {
            $this->di['db']->trash($assignment);
        }

        return true;
    }

    protected function sanitizeIds(array $ids): array
    {
        $result = [];

        foreach ($ids as $id) {
            $clean = (int) $id;
            if ($clean > 0 && !in_array($clean, $result, true)) {
                $result[] = $clean;
            }
        }

        return $result;
    }

    protected function normalizePricing($pricing): array
    {
        $pricing = is_array($pricing) ? $pricing : [];
        $result = [];

        foreach ($this->getPricingPeriods() as $code => $label) {
            $result[$code] = isset($pricing[$code]) ? round((float) $pricing[$code], 2) : 0.0;
        }

        return $result;
    }

    protected function decodePricing(?string $pricing): array
    {
        $decoded = $this->di['tools']->decodeJ($pricing);

        return $this->normalizePricing($decoded);
    }

    protected function buildSelectedOptionRow(
        \Model_Product $product,
        array $group,
        array $option,
        array $selected,
        ?string $period,
        bool $validate
    ): ?array {
        $optionId = (int) ($option['id'] ?? 0);
        $selectedValue = $selected[$optionId] ?? null;

        switch ($option['type']) {
            case self::TYPE_DROPDOWN:
            case self::TYPE_RADIO:
                if ($selectedValue === null || $selectedValue === '') {
                    if ($validate && !empty($option['required'])) {
                        throw new \Priyx_Exception('Please select ":option"', [':option' => $option['title']]);
                    }

                    return null;
                }

                foreach (($option['values'] ?? []) as $value) {
                    if ((int) $value['id'] !== (int) $selectedValue || !empty($value['hidden'])) {
                        continue;
                    }

                    $price = $this->getConfiguredPriceAmount($product, $value['pricing'] ?? [], $period);

                    return [
                        'group_id' => (int) ($group['id'] ?? 0),
                        'group_title' => $group['title'] ?? '',
                        'option_id' => $optionId,
                        'option_title' => $option['title'],
                        'type' => $option['type'],
                        'value_id' => (int) $value['id'],
                        'value_label' => $value['label'],
                        'quantity' => 1,
                        'unit_price' => $price,
                        'price' => round($price, 2),
                    ];
                }

                if ($validate) {
                    throw new \Priyx_Exception('Selected value for ":option" is not valid', [':option' => $option['title']]);
                }

                return null;

            case self::TYPE_YES_NO:
                $enabled = in_array((string) $selectedValue, ['1', 'true', 'on', 'yes'], true);
                if (!$enabled) {
                    if ($validate && !empty($option['required'])) {
                        throw new \Priyx_Exception('Please enable ":option"', [':option' => $option['title']]);
                    }

                    return null;
                }

                $price = $this->getConfiguredPriceAmount($product, $option['pricing'] ?? [], $period);

                return [
                    'group_id' => (int) ($group['id'] ?? 0),
                    'group_title' => $group['title'] ?? '',
                    'option_id' => $optionId,
                    'option_title' => $option['title'],
                    'type' => $option['type'],
                    'value_id' => null,
                    'value_label' => 'Yes',
                    'quantity' => 1,
                    'unit_price' => $price,
                    'price' => round($price, 2),
                ];

            case self::TYPE_QUANTITY:
                $qty = max(0, (int) $selectedValue);
                if ($qty < 1) {
                    if ($validate && !empty($option['required'])) {
                        throw new \Priyx_Exception('Please enter a value for ":option"', [':option' => $option['title']]);
                    }

                    return null;
                }

                $unitPrice = $this->getConfiguredPriceAmount($product, $option['pricing'] ?? [], $period);

                return [
                    'group_id' => (int) ($group['id'] ?? 0),
                    'group_title' => $group['title'] ?? '',
                    'option_id' => $optionId,
                    'option_title' => $option['title'],
                    'type' => $option['type'],
                    'value_id' => null,
                    'value_label' => (string) $qty,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'price' => round($unitPrice * $qty, 2),
                ];
        }

        return null;
    }

    protected function getProductPriceType(\Model_Product $product): string
    {
        if (!$product->product_payment_id) {
            return 'free';
        }

        $payment = $this->di['db']->load('ProductPayment', $product->product_payment_id);
        if (!$payment instanceof \Model_ProductPayment) {
            return 'free';
        }

        return (string) $payment->type;
    }
}
