<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Projects\CreateProjectAction;
use App\Enums\ProjectCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectApiRequest;
use App\Http\Requests\Projects\UpdateProjectApiRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * List projects with optional category filter and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $categoryParam = $request->query('category');
        $category = null;

        if ($categoryParam !== null && $categoryParam !== '') {
            $category = ProjectCategory::tryFrom((string) $categoryParam);
            if ($category === null) {
                return response()->json(['error' => 'Invalid project category'], 422);
            }
        }

        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));

        $query = Project::query()
            ->withCount(['scenes', 'characters', 'locations'])
            ->orderByDesc('created_at');

        if ($category !== null) {
            $query->category($category);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (Project $project): array => $this->serializeProject($project))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    /**
     * Create a new project via M2M API.
     */
    public function store(StoreProjectApiRequest $request, CreateProjectAction $action): JsonResponse
    {
        $userId = $request->validated('user_id');
        $owner = $action->resolveSystemOwner(is_string($userId) ? $userId : null);

        $project = $action->execute($owner, $request->toDto());
        $project->loadCount(['scenes', 'characters', 'locations']);

        return response()->json($this->serializeProject($project), 201);
    }

    /**
     * Retrieve a single project by UUID.
     */
    public function show(string $projectId): JsonResponse
    {
        $project = Project::query()
            ->withCount(['scenes', 'characters', 'locations'])
            ->find($projectId);

        if ($project === null) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        return response()->json($this->serializeProject($project), 200);
    }

    /**
     * Update an existing project by UUID.
     */
    public function update(UpdateProjectApiRequest $request, string $projectId): JsonResponse
    {
        $project = Project::query()->find($projectId);

        if ($project === null) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $project->update($request->validated());
        $project->loadCount(['scenes', 'characters', 'locations']);

        return response()->json($this->serializeProject($project), 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProject(Project $project): array
    {
        $category = $project->categoryEnum();

        return [
            'id' => $project->id,
            'title' => $project->title,
            'synopsis' => $project->synopsis,
            'status' => $project->status,
            'category' => $category->value,
            'category_label' => $category->label(),
            'storyboard_style' => $project->storyboard_style,
            'scenes_count' => (int) ($project->scenes_count ?? 0),
            'characters_count' => (int) ($project->characters_count ?? 0),
            'locations_count' => (int) ($project->locations_count ?? 0),
            'studio_url' => route('projects.show', $project),
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
        ];
    }
}
