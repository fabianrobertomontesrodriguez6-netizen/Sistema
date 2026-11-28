<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Traducción de las consultas (idioma detectado y traducciones guardadas) y la columna
 * vehiculo.version de la idea #9, que se había creado sin migración.
 */
final class Version20261128125835 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consultas: idioma_mensaje y traducciones. Vehículos: version (si falta).';
    }

    public function up(Schema $schema): void
    {
        // vehiculo.version se agregó en la idea #9 sin migración: solo se crea si no existe.
        if (!$schema->getTable('vehiculo')->hasColumn('version')) {
            $this->addSql('ALTER TABLE vehiculo ADD COLUMN version VARCHAR(80) DEFAULT NULL');
        }
        $this->addSql('ALTER TABLE consulta ADD COLUMN idioma_mensaje VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE consulta ADD COLUMN traducciones CLOB DEFAULT \'{}\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Solo deshace las columnas de consulta (vehiculo.version la usa el código desde la idea #9).
        $this->addSql('CREATE TEMPORARY TABLE __temp__consulta AS SELECT id, auto_nombre, nombre, email, telefono, mensaje, idioma, leida, creada_en, vehiculo_id, usuario_id FROM consulta');
        $this->addSql('DROP TABLE consulta');
        $this->addSql('CREATE TABLE consulta (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, auto_nombre VARCHAR(200) NOT NULL, nombre VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, telefono VARCHAR(40) DEFAULT NULL, mensaje CLOB NOT NULL, idioma VARCHAR(5) NOT NULL, leida BOOLEAN NOT NULL, creada_en DATETIME NOT NULL, vehiculo_id INTEGER DEFAULT NULL, usuario_id INTEGER DEFAULT NULL, CONSTRAINT FK_A6FE3FDE25F7D575 FOREIGN KEY (vehiculo_id) REFERENCES vehiculo (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A6FE3FDEDB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO consulta (id, auto_nombre, nombre, email, telefono, mensaje, idioma, leida, creada_en, vehiculo_id, usuario_id) SELECT id, auto_nombre, nombre, email, telefono, mensaje, idioma, leida, creada_en, vehiculo_id, usuario_id FROM __temp__consulta');
        $this->addSql('DROP TABLE __temp__consulta');
        $this->addSql('CREATE INDEX IDX_A6FE3FDE25F7D575 ON consulta (vehiculo_id)');
        $this->addSql('CREATE INDEX IDX_A6FE3FDEDB38439E ON consulta (usuario_id)');
    }
}
