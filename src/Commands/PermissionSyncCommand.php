<?php

namespace Nahid\Permit\Commands;

use Illuminate\Console\Command;
use Nahid\Permit\Permissions\PermissionRepository;
use Nahid\Permit\Users\UserRepository;
use Nahid\JsonQ\Jsonq;

class PermissionSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permit:permissions {cmd}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync permissions to dababase';


    protected $permissions;
    protected $roles;
    protected $user;
    protected $permission;
    protected $userColumn;
    protected $superUser;

    /**
     * Create a new command instance.
     */
    public function __construct(UserRepository $userRepository, PermissionRepository $permissionRepository)
    {
        parent::__construct();
        $this->permissions = config('permit.permissions');
        $this->roles = config('permit.roles');
        $this->roleColumn = config('permit.users.role_column');
        $this->superUser = config('permit.super_user');
        $this->user = $userRepository;
        $this->permission = $permissionRepository;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $command = $this->argument('cmd');
        $this->syncRolePermissions();
    }

    protected function syncRolePermissions()
    {

        $data = [];
        $jsonq = new Jsonq();
        $permission_object = $jsonq->collect($this->permissions);
        foreach($this->roles as $role=>$permission) {
            $permissions = [];
            foreach ($permission as $rules) {
                $rule = explode('.', $rules);

                $perms = $permission_object->node($rule[0])->get(true);
                if ($rule[1] == '*') {
                    if (!is_null($perms)) {
                        if(!isset($permissions[$rule[0]])) {
                            $permissions[$rule[0]] = [];
                        }

                        $auth_perms = [];
                        foreach ($perms as $permission) {
                            $auth_perms[$permission] = true;
                        }
                        $permissions[$rule[0]] = $auth_perms;
                    }
                }else{
                    if(!is_null($perms)) {
                        if(!isset($permissions[$rule[0]])) {
                            $permissions[$rule[0]] = [];
                        }


                        if (in_array($rule[1], $perms)) {
                            $permissions[$rule[0]][$rule[1]] = true;
                        }
                    }
                }
            }

            $data[] = ['role_name'=>$role, 'permission'=>json_encode($permissions)];
        }

        $db = app('db');
        if(is_array($data)) {
            if ($this->confirm('Do you wish to sync with existing permissions?')) {
                $db->beginTransaction();
                foreach($data as $d) {
                    if ($this->permission->syncRolePermissions($d['role_name'], $d)) {
                        $db->commit();
                    } else {
                        $db->rollback();
                    }
                }

                $this->info('Permissions Synced!');
            } else {
                $this->error('Process Canceled!');
            }
        }
    }
}