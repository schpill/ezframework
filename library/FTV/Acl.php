<?php
    /**
     * ACL class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */

    class FTV_Acl
    {
        protected $_datas = array();

        public function __construct()
        {
            $this->_datas['roleModel']          = em(config('app.roles.entity'), config('app.roles.table'));
            $this->_datas['userModel']          = em(config('app.users.entity'), config('app.users.table'));
            $this->_datas['canRoles']           = array();
            $this->_datas['cannotRoles']        = array();
            $this->_datas['canUsers']           = array();
            $this->_datas['cannotUsers']        = array();
        }

        public function canByRole($role)
        {
            $class = get_parent_class($this->_datas['roleModel']);
            if (!$role instanceof $class) {
                throw new e('The role is not in correct format.');
            }
            if (!in_array($role->getId(), $this->_datas['canRoles'])) {
                $this->_datas['canRoles'][] = $role->getId();
            }
            return $this;
        }

        public function cannotByRole($role)
        {
            $class = get_parent_class($this->_datas['roleModel']);
            if (!$role instanceof $class) {
                throw new e('The role is not in correct format.');
            }
            if (!in_array($role->getId(), $this->_datas['cannotRoles'])) {
                $this->_datas['cannotRoles'][] = $role->getId();
            }
            return $this;
        }

        public function canByUser()
        {
            $user = u::get('FTVUser');
            if (!in_array($user->getId(), $this->_datas['canUsers'])) {
                $this->_datas['canUsers'][] = $user->getId();
            }
            return $this;
        }

        public function cannotByUser()
        {
            $user = u::get('FTVUser');
            if (!in_array($user->getId(), $this->_datas['cannotUsers'])) {
                $this->_datas['cannotUsers'][] = $user->getId();
            }
            return $this;
        }

        public function checkAccessModule()
        {
            $user           = u::get('FTVUser');
            if (null !== $user) {
                $aclRules   = u::get('FTVConfigAcl');
                $module     = u::get('FTVModuleName');
                $controller = u::get('FTVControllerName');
                $action     = u::get('FTVActionName');
                $module     = u::get('FTVModuleName');
                $userRoles  = em(config('app.usersroles.entity'), config('app.usersroles.table'))->fetch()->findByAccountId($user->getId());
                $aclRoles   = config('app.acl.roles');

                /* on regarde s'il y a une restriction d acces au module, on prenant garde de pouvoir afficher les pages statiques no-right et is-404 */

                if (ake($module, $aclRules) && 'no-right' != $action && 'is-404' != $action) {
                    if (ake('cannotByRole', $aclRules[$module])) {
                        $access = false;
                        foreach ($aclRoles as $aclRole) {
                            foreach ($userRoles as $userRole) {
                                $role  = $this->_datas['roleModel']->find($userRole->getRoleId())->getRoleName();
                                if (!in_array($role, $aclRules[$module]['cannotByRole']) && in_array($role, $aclRoles)) {
                                    $access = true;
                                }
                            }
                        }
                        if (false === $access) {
                            u::redirect('FTV_noright');
                            exit;
                        }
                    }
                }
            }
        }

        public function check()
        {
            $user       = u::get('FTVUser');
            $aclRoles   = config('app.acl.roles');
            $adminRole  = $this->_datas['roleModel']->findByRoleName(config('app.role.admin'));
            $userRoles  = em(config('app.usersroles.entity'), config('app.usersroles.table'))->findByAccountId($user->getId());
            if (count($userRoles) == 1) {
                $userRoles = array($userRoles);
            }

            // check if role is allowed in application
            $continue = false;
            foreach ($userRoles as $uRole) {
                $roleName = em(config('app.roles.entity'), config('app.roles.table'))->find($uRole->getRoleId())->getRoleName();
                $continue = in_array($roleName, $aclRoles);
                if (true === $continue) {
                    break;
                }
            }

            if (false === $continue) {
                u::redirect('FTV_noright');
                exit;
            }


            // check by user cannot
            if (count($this->_datas['cannotUsers'])) {
                if (in_array($user->getId(), $this->_datas['cannotUsers'])) {
                    u::redirect('FTV_noright');
                    exit;
                }
            }

            // check by role cannot
            if (count($this->_datas['cannotRoles'])) {
                foreach ($this->_datas['cannotRoles'] as $idRole) {
                    foreach ($userRoles as $uRole) {
                        $uRoleId = $uRole->getRoleId();
                        if ($idRole == $uRoleId) {
                            u::redirect('FTV_noright');
                            exit;
                        }
                    }
                }
            }

            // check by user can
            if (count($this->_datas['canUsers'])) {
                if (in_array($user->getId(), $this->_datas['canUsers'])) {
                    return $this;
                }
            }

            // check by role can
            if (count($this->_datas['canRoles'])) {
                foreach ($this->_datas['canRoles'] as $idRole) {
                    foreach ($userRoles as $uRole) {
                        $uRoleId = $uRole->getRoleId();
                        if ($idRole == $uRoleId) {
                            return $this;
                        }
                    }
                }
            }

            // check if admin Role
            foreach ($userRoles as $uRole) {
                $idRole = $uRole->getRoleId();
                if ($idRole == $adminRole->getId()) {
                    return $this;
                }
            }
        }
    }
