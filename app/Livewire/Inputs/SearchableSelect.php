<?php

namespace App\Livewire\Inputs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Attributes\Modelable;
use Livewire\Component;

class SearchableSelect extends Component
{
    #[Modelable]
    public $value = null;
    public string $model;
    public array $searchFields = [];
    public array $displayFields = [];
    public string $placeholder;
    public int $maxResults;
    public string $maxWidth;
    public string $inputClasses;
    public string $dropdownClasses;
    public string $valueField;
    public array $constraints = [];
    public bool $clearable = true;
    public ?string $inputName = null;
    public array $includeFields = [];
    public bool $showAllWhenEmpty = true;
    public string $search = '';
    public array $results = [];
    public bool $dropdownOpen = false;
    public ?array $selectedRecord = null;

    protected bool $syncingSelection = false;

    public function mount(
        string $model,
        ?array $searchFields = null,
        ?array $displayFields = null,
        string $placeholder = 'Search…',
        int $maxResults = 8,
        string $maxWidth = 'auto',
        string $inputClasses = 'input input-bordered',
        string $dropdownClasses = 'bg-base-100 border border-base-200 rounded-xl shadow-lg w-full',
        string $valueField = 'id',
        ?array $constraints = null,
        bool $clearable = true,
        ?string $inputName = null,
        ?array $includeFields = null,
        bool $showAllWhenEmpty = true,
    ): void {
        abort_unless(class_exists($model) && is_subclass_of($model, Model::class), 400, 'Invalid model for searchable select.');

        $this->model = $model;
        $this->searchFields = $searchFields ?: ['name'];
        $this->displayFields = $displayFields ?: ['name'];
        $this->placeholder = $placeholder;
        $this->maxResults = $maxResults;
        $this->maxWidth = $maxWidth;
        $this->inputClasses = $inputClasses;
        $this->dropdownClasses = $dropdownClasses;
        $this->valueField = $valueField;
        $this->constraints = $constraints ?? [];
        $this->clearable = $clearable;
        $this->inputName = $inputName;
        $this->includeFields = $includeFields ?? [];
        $this->showAllWhenEmpty = $showAllWhenEmpty;

        if ($this->value) {
            $this->hydrateSelected();
        }
    }

    public function updatedValue($value): void
    {
        if ($value) {
            $this->hydrateSelected();
        } else {
            $this->selectedRecord = null;
            $this->setSearch('');
        }
    }

    public function updatedSearch(): void
    {
        if ($this->syncingSelection) {
            return;
        }

        $term = trim($this->search ?? '');

        if ($term === '') {
            if ($this->showAllWhenEmpty) {
                $this->dropdownOpen = true;
                $this->results = $this->defaultResults();
            } else {
                $this->results = [];
                $this->dropdownOpen = false;
            }
            return;
        }

        $this->dropdownOpen = true;
        $this->performSearch($term);
    }

    public function select($recordId): void
    {
        $record = $this->baseQuery()->where($this->valueField, $recordId)->first();

        if (! $record) {
            return;
        }

        $this->value = $record->{$this->valueField};
        $this->selectedRecord = $this->formatRecord($record);
        $this->setSearch($this->selectedRecord['label']);
        $this->dropdownOpen = false;
        $this->results = [];

        if ($this->inputName) {
            $this->dispatch('searchable-select-selected', field: $this->inputName, value: $this->value, label: $this->selectedRecord['label'], data: $this->selectedRecord['data'] ?? []);
        }
    }

    // todo: delete this function? daisyui has its own clear button handling
    public function clearSelection(): void
    {
        if (! $this->clearable) {
            return;
        }

        $this->value = null;
        $this->selectedRecord = null;
        $this->setSearch('');
        $this->results = [];
        $this->dropdownOpen = false;

        if ($this->inputName) {
            $this->dispatch('searchable-select-selected', field: $this->inputName, value: null, label: null, data: []);
        }
    }

    public function openDropdown(): void
    {
        $this->dropdownOpen = true;

        if (blank(trim($this->search))) {
            if ($this->selectedRecord) {
                $this->setSearch($this->selectedRecord['label']);
            }

            if ($this->showAllWhenEmpty) {
                $this->results = $this->defaultResults();
            }
        }
    }

    public function closeDropdown(): void
    {
        $this->dropdownOpen = false;
    }

    public function render()
    {
        return view('livewire.inputs.searchable-select');
    }

    public function setConstraints(array $constraints): void
    {
        $this->constraints = $constraints;

        $exists = $this->value
            ? $this->baseQuery()->where($this->valueField, $this->value)->exists()
            : false;

        if (! $exists) {
            $this->clearSelection();
        }
    }

    protected function hydrateSelected(): void
    {
        $record = $this->baseQuery()->where($this->valueField, $this->value)->first();

        if ($record) {
            $this->selectedRecord = $this->formatRecord($record);
            $this->setSearch($this->selectedRecord['label']);
            if ($this->inputName) {
                $this->dispatch('searchable-select-selected', field: $this->inputName, value: $this->value, label: $this->selectedRecord['label'], data: $this->selectedRecord['data'] ?? []);
            }
        }
    }

    protected function performSearch(string $term): void
    {
        $tokens = array_filter(explode(' ', Str::lower($term)));

        $query = $this->baseQuery();

        $query->where(function (Builder $builder) use ($term, $tokens) {
            foreach ($this->searchFields as $field) {
                $builder->orWhere($field, 'like', '%' . $term . '%');

                foreach ($tokens as $token) {
                    $builder->orWhere($field, 'like', '%' . $token . '%');
                }
            }
        });

        $columns = array_unique(array_merge([$this->valueField], $this->searchFields, $this->displayFields));

        $this->results = $query
            ->limit($this->maxResults)
            ->get($columns)
            ->map(fn ($record) => $this->formatRecord($record))
            ->values()
            ->toArray();
    }

    protected function baseQuery(): Builder
    {
        /** @var Builder $query */
        $query = ($this->model)::query();

        foreach ($this->constraints as $field => $constraint) {
            if (is_callable($constraint)) {
                $constraint($query);
                continue;
            }

            if (is_array($constraint)) {
                $query->whereIn($field, $constraint);
            } else {
                $query->where($field, $constraint);
            }
        }

        return $query;
    }

    protected function formatRecord(Model $record): array
    {
        $data = $record->toArray();
        $labelField = $this->displayFields[0] ?? $this->valueField;
        $label = data_get($data, $labelField, '—');
        $subLabel = collect($this->displayFields)
            ->skip(1)
            ->map(fn ($field) => data_get($data, $field))
            ->filter()
            ->implode(' • ');

        return [
            'id' => $record->{$this->valueField},
            'label' => $label,
            'subLabel' => $subLabel,
            'data' => Arr::only($data, $this->includeFields),
        ];
    }

    protected function setSearch(string $value): void
    {
        $this->syncingSelection = true;
        $this->search = $value;
        $this->syncingSelection = false;
    }

    protected function defaultResults(): array
    {
        $columns = array_unique(array_merge([$this->valueField], $this->searchFields, $this->displayFields, $this->includeFields));

        return $this->baseQuery()
            ->limit($this->maxResults)
            ->orderBy($this->displayFields[0] ?? $this->valueField)
            ->get($columns)
            ->map(fn ($record) => $this->formatRecord($record))
            ->values()
            ->toArray();
    }
}
