<?php
/**
 * This file is part of ClearDB plugin for FacturaScripts
 * Copyright (C) 2022-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Core\Telemetry;
use FacturaScripts\Core\Tools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ClearDB extends Controller
{
    /** @var bool */
    public $telemetryRegistered = false;

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

        // Comprobar si la instalación está registrada
        $telemetry = Telemetry::init();
        $this->telemetryRegistered = $telemetry->ready();

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

        // Verificar que el motor de base de datos sea MySQL o MariaDB
        if (Tools::config('db_type') !== 'mysql') {
            Tools::log()->warning('Este plugin solo es compatible con MySQL/MariaDB');
            return;
        }

        // Desvincular la instalación si está registrada
        $telemetry = Telemetry::init();
        if ($telemetry->ready()) {
            if (false === $telemetry->unlink()) {
                Tools::log()->error('No se pudo desvincular la instalación');
                return;
            }
            Tools::log()->info('Instalación desvinculada correctamente');
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

        // Eliminar archivos JSON de la carpeta MyFiles
        $myFilesPath = FS_FOLDER . '/MyFiles/';
        $filesToDelete = ['db-updater.json', 'db-changelog.json', 'migrations.json'];
        foreach ($filesToDelete as $file) {
            $filePath = $myFilesPath . $file;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        DbUpdater::rebuild();
        Cache::clear();

        $this->redirect('login');
    }
}
