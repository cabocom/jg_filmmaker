<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determina si el usuario puede visualizar y editar el proyecto en el estudio.
     * Política de privacidad estricta: sólo el creador/dueño legítimo del proyecto tiene acceso.
     * Los administradores no tienen permiso de acceso al editor creativo de otros usuarios.
     */
    public function view(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }

    /**
     * Determina si el usuario puede modificar la metadata o configuración del proyecto.
     */
    public function update(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }

    /**
     * Determina si el usuario puede eliminar el proyecto.
     */
    public function delete(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }
}
