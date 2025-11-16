<?php

namespace App\Livewire;

use App\Models\Category;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Categories extends Component
{
    // #[Validate(['required' , 'string' , 'uniqe:categories'])]
    public $name = '';
    public $color = '#3B82F6';
    public $icon = '';
    public $isEditing = false;
    public $editingId = null;
    public $colors = [
        '#EF4444', // Red
        '#F97316', // Orange
        '#F59E0B', // Amber
        '#EAB308', // Yellow
        '#84CC16', // Lime
        '#22C55E', // Green
        '#10B981', // Emerald
        '#14B8A6', // Teal
        '#06B6D4', // Cyan
        '#0EA5E9', // Sky
        '#3B82F6', // Blue
        '#6366F1', // Indigo
        '#8B5CF6', // Violet
        '#A855F7', // Purple
        '#D946EF', // Fuchsia
        '#EC4899', // Pink
        '#F43F5E', // Rose
    ];

    // computed properties
    #[Computed]
    public function categories()
    {
        return Category::withCount('expenses')
            ->where('user_id', auth()->user()->id)
            ->orderBy('name', 'asc')
            ->get();
    }


    // start validation
    protected function rules()
    {
        return [
            'name' => 'required|string|unique:categories,name,' . ($this->editingId ?: 'NULL') . ',id,user_id,' . auth()->id(),
            'color' => 'required|string',
            'icon' => 'nullable|string',
        ];
    }
    protected $messages = [
        'name.required' => 'The category name field is required.',
        'name.string' => 'The name must be a string.',
        'name.unique' => 'The name has already been taken.',
        'color.required' => 'The color field is required.',
        'color.string' => 'The color must be a string.',
        'icon.string' => 'The icon must be a string.',
    ];
    // end validation


    // save new or update category
    public function save()
    {
        $this->validate();

        if ($this->isEditing) {
            $category = Category::findOrFail($this->editingId);
            if ($category->user_id != auth()->id()) {
                session()->flash('error', 'You are not authorized to edit this category.');
                return;
            }

            $category->update([
                'name' => $this->name,
                'color' => $this->color,
                'icon' => $this->icon,
            ]);

            session()->flash('message', 'Category updated successfully.');

        } else {
            $category = Category::create([
                'name' => $this->name,
                'color' => $this->color,
                'icon' => $this->icon,
                'user_id' => auth()->id(),
            ]);
            session()->flash('message', 'Category careated successfully.');
        }
        $this->reset(['name', 'color', 'icon', 'isEditing', 'editingId']);
    }
    public function delete($categoryId)
    {
        $category = Category::findOrFail($categoryId);
        if ($category->user_id != auth()->id()) {
            session()->flash('error', 'You are not authorized to delete this category.');
            return;
        }
        $category->delete();
        session()->flash('message', 'Category deleted successfully.');
    }

    public function edit($categoryId)
    {
        $category = Category::findOrFail($categoryId);
        if ($category) {
            $this->name = $category->name;
            $this->color = $category->color;
            $this->icon = $category->icon;
            $this->isEditing = true;
            $this->editingId = $category->id;
        }
    }
    public function cancelEdit()
    {
        $this->reset(['name', 'icon', 'isEditing', 'editingId']);
        $this->color = '#3B82F6';
    }


    public function render()
    {
        return view(
            'livewire.categories',
            ['categories' => $this->categories]
        );
    }
}
