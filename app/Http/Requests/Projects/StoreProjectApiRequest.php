<?php

declare(strict_types=1);

namespace App\Http\Requests\Projects;

use App\DTOs\Projects\CreateProjectDTO;
use App\Enums\ProjectCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectApiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'synopsis' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', 'in:draft,in_progress,completed'],
            'category' => ['sometimes', 'string', 'in:personal,ai_video'],
            'storyboard_style' => ['nullable', 'string', 'max:64'],
            'raw_text' => ['nullable', 'string', 'max:50000'],
            'slugline' => ['nullable', 'string', 'max:180'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
        ];
    }

    /**
     * Convert the validated request to a CreateProjectDTO.
     */
    public function toDto(): CreateProjectDTO
    {
        $validated = $this->validated();

        return new CreateProjectDTO(
            title: (string) $validated['title'],
            synopsis: isset($validated['synopsis']) ? (string) $validated['synopsis'] : null,
            status: (string) ($validated['status'] ?? 'draft'),
            category: ProjectCategory::fromNullable(isset($validated['category']) ? (string) $validated['category'] : null),
            storyboardStyle: isset($validated['storyboard_style']) ? (string) $validated['storyboard_style'] : null,
            rawText: isset($validated['raw_text']) ? (string) $validated['raw_text'] : null,
            slugline: isset($validated['slugline']) ? (string) $validated['slugline'] : null,
        );
    }
}
