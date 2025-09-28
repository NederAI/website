<?php
namespace App\Services\Intranet;

use Core\Container;
use Core\File;

class ModuleRegistry {
    private File $file;
    /** @var array<string,array> */
    private array $moduleIndex = [];
    /** @var array<int,array> */
    private array $modules = [];

    public function __construct(Container $container) {
        $this->file = $container->get(File::class);
        $this->loadModules();
    }

    /**
     * Return all modules that are visible for the given roles.
     */
    public function modulesForRoles(array $roles): array {
        return array_values(array_map(
            fn(array $module) => $this->presentModule($module),
            array_filter($this->modules, fn(array $module) => $this->isModuleVisible($module, $roles))
        ));
    }

    /**
     * Return simple navigation entries for the given roles.
     */
    public function navigationForRoles(array $roles): array {
        $items = [];
        foreach ($this->modules as $module) {
            if (!$this->isModuleVisible($module, $roles)) {
                continue;
            }
            $items[] = [
                'id' => $module['id'],
                'label' => $module['label'],
                'icon' => $module['icon'],
                'category' => $module['category'],
                'description' => $module['description'],
            ];
        }
        return $items;
    }

    /**
     * Locate a module by id. Returns null when the module does not exist.
     */
    public function find(string $moduleId): ?array {
        $id = $this->sanitizeId($moduleId);
        return $this->moduleIndex[$id] ?? null;
    }

    /**
     * Get an automation for a module.
     */
    public function findAutomation(string $moduleId, string $automationId): ?array {
        $module = $this->find($moduleId);
        if ($module === null) {
            return null;
        }
        $automationKey = $this->sanitizeAutomationId($automationId);
        return $module['automation_map'][$automationKey] ?? null;
    }

    /**
     * Reload module definitions from disk.
     */
    public function reload(): void {
        $this->loadModules();
    }

    private function loadModules(): void {\r\n        \->modules = [];\r\n        \->moduleIndex = [];\r\n\r\n        \ = 'config/intranet-modules.php';\r\n        if (!\->file->exists(\)) {\r\n            return;\r\n        }\r\n        \ = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, \);\r\n\r\n        \ = require \;\r\n        if (!is_array(\)) {\r\n            return;\r\n        }\r\n\r\n        foreach (\ as \) {\r\n            if (!is_array(\)) {\r\n                continue;\r\n            }\r\n            \ = \->normaliseModule(\);\r\n            if (\ === null) {\r\n                continue;\r\n            }\r\n            \->modules[] = \;\r\n            \->moduleIndex[\['id']] = \;\r\n        }\r\n    }\r\n\r\n    private function normaliseModule(array $raw): ?array {
        if (empty($raw['id'])) {
            return null;
        }
        $id = $this->sanitizeId((string) $raw['id']);
        if ($id === '') {
            return null;
        }

        $module = [
            'id' => $id,
            'label' => $this->normaliseLabel($raw['label'] ?? $id),
            'icon' => $this->normaliseIcon($raw['icon'] ?? 'widgets'),
            'description' => isset($raw['description']) ? trim((string) $raw['description']) : '',
            'category' => $this->normaliseCategory($raw['category'] ?? 'general'),
            'roles' => $this->normaliseRoles($raw['roles'] ?? []),
            'widgets' => $this->normaliseWidgets($raw['widgets'] ?? []),
        ];

        [$automationList, $automationMap] = $this->normaliseAutomations($raw['automations'] ?? []);
        $module['automations'] = $automationList;
        $module['automation_map'] = $automationMap;

        return $module;
    }

    private function normaliseLabel(string $label): string {
        $label = trim($label);
        if ($label === '') {
            return 'Module';
        }
        return $label;
    }

    private function normaliseIcon(string $icon): string {
        $icon = trim($icon);
        if ($icon === '') {
            return 'widgets';
        }
        return preg_replace('/[^a-z0-9._-]+/i', '', $icon) ?: 'widgets';
    }

    private function normaliseCategory(string $category): string {
        $category = strtolower(trim($category));
        if ($category === '') {
            return 'general';
        }
        return preg_replace('/[^a-z0-9._-]+/i', '-', $category) ?: 'general';
    }

    private function normaliseRoles($roles): array {
        if (!is_array($roles)) {
            return [];
        }
        $normalised = [];
        foreach ($roles as $role) {
            $role = strtolower(trim((string) $role));
            if ($role !== '') {
                $normalised[] = $role;
            }
        }
        return array_values(array_unique($normalised));
    }

    private function normaliseWidgets($widgets): array {
        if (!is_array($widgets)) {
            return [];
        }
        $normalised = [];
        $index = 0;
        foreach ($widgets as $widget) {
            if (!is_array($widget)) {
                continue;
            }
            $type = $this->normaliseWidgetType($widget['type'] ?? 'info');
            $id = isset($widget['id']) ? $this->sanitizeId((string) $widget['id']) : '';
            if ($id === '') {
                $id = $type . '-' . $index;
            }
            $payload = [
                'id' => $id,
                'type' => $type,
                'title' => isset($widget['title']) ? trim((string) $widget['title']) : '',
                'support' => isset($widget['support']) ? trim((string) $widget['support']) : null,
                'body' => isset($widget['body']) ? trim((string) $widget['body']) : null,
                'actions' => $this->normaliseActions($widget['actions'] ?? []),
            ];
            if ($type === 'list') {
                $payload['items'] = $this->normaliseListItems($widget['items'] ?? []);
            }
            if ($type === 'form') {
                $payload['fields'] = $this->normaliseFields($widget['fields'] ?? []);
                $payload['submit_label'] = isset($widget['submit_label']) ? trim((string) $widget['submit_label']) : 'Opslaan';
                $payload['automation'] = isset($widget['automation']) ? $this->sanitizeAutomationId((string) $widget['automation']) : '';
            }
            $normalised[] = $payload;
            $index++;
        }
        return $normalised;
    }

    private function normaliseWidgetType(string $type): string {
        $type = strtolower(trim($type));
        $allowed = ['info', 'list', 'form', 'metrics'];
        if (!in_array($type, $allowed, true)) {
            return 'info';
        }
        return $type;
    }

    private function normaliseActions($actions): array {
        if (!is_array($actions)) {
            return [];
        }
        $normalised = [];
        foreach ($actions as $action) {
            if (!is_array($action) || empty($action['automation'])) {
                continue;
            }
            $automation = $this->sanitizeAutomationId((string) $action['automation']);
            if ($automation === '') {
                continue;
            }
            $id = isset($action['id']) ? $this->sanitizeId((string) $action['id']) : $automation;
            if ($id === '') {
                $id = $automation;
            }
            $style = strtolower(trim((string) ($action['style'] ?? 'primary')));
            if (!in_array($style, ['primary', 'secondary', 'ghost'], true)) {
                $style = 'primary';
            }
            $normalised[] = [
                'id' => $id,
                'label' => $this->normaliseLabel($action['label'] ?? $id),
                'style' => $style,
                'automation' => $automation,
                'confirm' => isset($action['confirm']) ? trim((string) $action['confirm']) : null,
            ];
        }
        return $normalised;
    }

    private function normaliseListItems($items): array {
        if (!is_array($items)) {
            return [];
        }
        $values = [];
        foreach ($items as $item) {
            $text = trim((string) $item);
            if ($text !== '') {
                $values[] = $text;
            }
        }
        return $values;
    }

    private function normaliseAutomations($automations): array {
        if (!is_array($automations)) {
            return [[], []];
        }
        $list = [];
        $map = [];
        foreach ($automations as $automation) {
            if (!is_array($automation) || empty($automation['id'])) {
                continue;
            }
            $id = $this->sanitizeAutomationId((string) $automation['id']);
            if ($id === '') {
                continue;
            }
            $entry = [
                'id' => $id,
                'label' => $this->normaliseLabel($automation['label'] ?? $id),
                'description' => isset($automation['description']) ? trim((string) $automation['description']) : '',
                'fields' => $this->normaliseFields($automation['fields'] ?? []),
                'success_message' => isset($automation['success_message']) ? trim((string) $automation['success_message']) : 'Actie verwerkt.',
                'category' => $this->normaliseCategory($automation['category'] ?? 'default'),
            ];
            $list[] = $entry;
            $map[$id] = $entry;
        }
        return [$list, $map];
    }

    private function normaliseFields($fields): array {
        if (!is_array($fields)) {
            return [];
        }
        $normalised = [];
        $index = 0;
        foreach ($fields as $field) {
            if (!is_array($field) || empty($field['id'])) {
                continue;
            }
            $id = $this->sanitizeId((string) $field['id']);
            if ($id === '') {
                $id = 'field_' . $index;
            }
            $type = $this->normaliseFieldType($field['type'] ?? 'text');
            $entry = [
                'id' => $id,
                'type' => $type,
                'label' => $this->normaliseLabel($field['label'] ?? $id),
                'required' => !empty($field['required']),
                'placeholder' => isset($field['placeholder']) ? trim((string) $field['placeholder']) : null,
                'default' => $field['default'] ?? null,
                'help' => isset($field['help']) ? trim((string) $field['help']) : null,
            ];
            if ($type === 'select' || $type === 'choice') {
                $entry['options'] = $this->normaliseOptions($field['options'] ?? []);
            }
            $normalised[] = $entry;
            $index++;
        }
        return $normalised;
    }

    private function normaliseFieldType(string $type): string {
        $type = strtolower(trim($type));
        $allowed = ['text', 'textarea', 'boolean', 'select', 'choice', 'number', 'date'];
        if (!in_array($type, $allowed, true)) {
            return 'text';
        }
        if ($type === 'choice') {
            return 'select';
        }
        return $type;
    }

    private function normaliseOptions($options): array {
        if (!is_array($options)) {
            return [];
        }
        $values = [];
        foreach ($options as $option) {
            if (is_array($option)) {
                if (empty($option['value'])) {
                    continue;
                }
                $value = (string) $option['value'];
                $label = $this->normaliseLabel($option['label'] ?? $value);
                $values[] = ['value' => $value, 'label' => $label];
                continue;
            }
            $value = trim((string) $option);
            if ($value === '') {
                continue;
            }
            $values[] = ['value' => $value, 'label' => $value];
        }
        return $values;
    }

    private function presentModule(array $module): array {
        $presented = $module;
        unset($presented['automation_map']);
        return $presented;
    }

    private function isModuleVisible(array $module, array $roles): bool {
        if (empty($module['roles'])) {
            return true;
        }
        $roles = array_map('strtolower', $roles);
        foreach ($module['roles'] as $required) {
            if (in_array($required, $roles, true)) {
                return true;
            }
        }
        return false;
    }

    private function sanitizeId(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value);
        return trim($value, '-');
    }

    private function sanitizeAutomationId(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._.-]+/', '-', $value);
        return trim($value, '-');
    }
}