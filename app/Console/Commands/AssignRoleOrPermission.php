<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AssignRoleOrPermission extends Command
{
    protected $signature = 'user:assign
        {type : Tipo de atribuição (role ou permission)}
        {name : Nome da role ou permission}
        {--id= : ID do usuário}
        {--email= : Email do usuário}';

    protected $description = 'Atribui uma role ou permission a um usuário pelo ID ou email. Cria a role/permission se não existir.';

    public function handle(): int
    {
        $type = $this->argument('type');
        $name = $this->argument('name');
        $userId = $this->option('id');
        $userEmail = $this->option('email');

        if (!in_array($type, ['role', 'permission'])) {
            $this->error('Tipo inválido. Use "role" ou "permission".');
            return self::FAILURE;
        }

        if (!$userId && !$userEmail) {
            $this->error('Informe --id ou --email para identificar o usuário.');
            return self::FAILURE;
        }

        // Find user
        $user = $userId
            ? User::find($userId)
            : User::where('email', $userEmail)->first();

        if (!$user) {
            $this->error('Usuário não encontrado.');
            return self::FAILURE;
        }

        // Create role/permission if not exists
        if ($type === 'role') {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $user->assignRole($role);
            $this->info("Role \"{$name}\" atribuída ao usuário {$user->email} (ID: {$user->id}).");
        } else {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
            $this->info("Permission \"{$name}\" atribuída ao usuário {$user->email} (ID: {$user->id}).");
        }

        return self::SUCCESS;
    }
}
