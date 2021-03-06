<?php
namespace Caco\Upgrade\CLI;

use Caco\CLI\AbstractCLI;
use Caco\Config\Model\Config;
use Caco\Icon\Model\Icon;
use RuntimeException;

/**
 * Upgrades the DB from version 2 to 3.
 *
 * Class From2To3
 * @packageCaco\Upgrade\CLI
 * @author Guido Krömer <mail 64 cacodaemon 46 de>
 */
class From2To3 extends AbstractCLI
{
    private $types = ['bookmark', 'feed'];

    /**
     * Runs the cli
     *
     * @return int
     */
    public function run()
    {
        $config = new Config;
        $config->readKey('database-version');

        if ($config->value != 2) {
            throw new RuntimeException("Database Version is \"$config->value\" but should be \"2\"!");
        }

        $this->upgradeDb();
        $this->importIcons();

        return 0;
    }

    /**
     * Upgrades the DB from Version 2 to 3.
     */
    protected function upgradeDb() {
        $this->printLine('Upgrading the DB…');
        $pdo = (new Icon)->getPdo();

        $sql = 'CREATE TABLE IF NOT EXISTS icon (
                  id INTEGER PRIMARY KEY,
                  inserted INTEGER NOT NULL,
                  id_feed INTEGER UNIQUE,
                  id_bookmark INTEGER UNIQUE,
                  data BLOB NOT NULL,
                  FOREIGN KEY (id_feed) REFERENCES feed(id) ON DELETE CASCADE
                  FOREIGN KEY (id_bookmark) REFERENCES bookmark(id) ON DELETE CASCADE
                );
                CREATE INDEX IF NOT EXISTS fk_icon_id_feed ON icon (id_feed);
                CREATE INDEX IF NOT EXISTS fk_icon_id_bookmark ON icon (id_bookmark);';

        $pdo->exec($sql);

        $config = new Config;
        $config->readKey('database-version');
        $config->value = 3;
        $config->save();
        $this->printLine('DB upgraded to 3!');
    }

    /**
     * Imports the favicons into the database and deletes them from filesystem.
     */
    protected function importIcons()
    {
        $this->printLine('Importing icons…');
        $iconsDir = sprintf('%s/public/icons', getcwd());

        foreach ($this->types as $type) {
            $path = "$iconsDir/$type/*.ico";
            foreach (glob($path) as $file) {
                $icon = new Icon;
                $icon->data = file_get_contents($file);
                if ($type === 'feed') {
                    $icon->id_feed     = str_replace('.ico', '', basename($file));
                } else {
                    $icon->id_bookmark = str_replace('.ico', '', basename($file));
                }

                if ($icon->save()) {
                    unlink($file);
                }
            }
        }
        $this->printLine('Icons imported!');
    }
}