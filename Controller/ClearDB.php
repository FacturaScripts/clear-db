<?php
/**
 * This file is part of ClearDB plugin for FacturaScripts
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\ClearDB\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Cache;
use FacturaScripts\Core\DbUpdater;
use FacturaScripts\Core\Tools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ClearDB extends Controller
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'reset-fs';
        $data['icon'] = 'fa-solid fa-trash-alt';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        if ($this->request->get('action', '') === 'reset-fs') {
            $this->resetFS();
        }
    }

    protected function resetFS(): void
    {
        if (false === $this->permissions->allowDelete) {
            Tools::log()->warning('not-allowed-delete');
            return;
        } elseif (false === $this->validateFormToken()) {
            return;
        }

        $database = new DataBase();
        $database->beginTransaction();
        $database->exec('SET FOREIGN_KEY_CHECKS = 0;');
        foreach ($database->getTables() as $table) {
            if (false === $database->exec('DROP TABLE ' . $table)) {
                Tools::log()->error('db-error-drop-tables', ['%tablename%' => $table]);
                $database->rollback();
                return;
            }
        }

        $database->exec('SET FOREIGN_KEY_CHECKS = 1;');
        $database->commit();

        DbUpdater::rebuild();
        Cache::clear();

        $this->redirect('Wizard');
    }
}
