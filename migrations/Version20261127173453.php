<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261127173453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tablas de usuarios, vehículos y consultas (y la cola de Messenger).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE consulta (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, auto_nombre VARCHAR(200) NOT NULL, nombre VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, telefono VARCHAR(40) DEFAULT NULL, mensaje CLOB NOT NULL, idioma VARCHAR(5) NOT NULL, leida BOOLEAN NOT NULL, creada_en DATETIME NOT NULL, vehiculo_id INTEGER DEFAULT NULL, usuario_id INTEGER DEFAULT NULL, CONSTRAINT FK_A6FE3FDE25F7D575 FOREIGN KEY (vehiculo_id) REFERENCES vehiculo (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A6FE3FDEDB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_A6FE3FDE25F7D575 ON consulta (vehiculo_id)');
        $this->addSql('CREATE INDEX IDX_A6FE3FDEDB38439E ON consulta (usuario_id)');
        $this->addSql('CREATE TABLE usuario (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, nombre VARCHAR(120) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, creado_en DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2265B05DE7927C74 ON usuario (email)');
        $this->addSql('CREATE TABLE vehiculo (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, slug VARCHAR(120) NOT NULL, marca VARCHAR(80) NOT NULL, modelo VARCHAR(80) NOT NULL, anio INTEGER NOT NULL, precio INTEGER NOT NULL, kilometraje INTEGER NOT NULL, combustible VARCHAR(40) NOT NULL, transmision VARCHAR(40) NOT NULL, carroceria VARCHAR(40) NOT NULL, motor VARCHAR(80) DEFAULT NULL, potencia INTEGER DEFAULT NULL, torque INTEGER DEFAULT NULL, traccion VARCHAR(40) DEFAULT NULL, aceleracion DOUBLE PRECISION DEFAULT NULL, velocidad_maxima INTEGER DEFAULT NULL, descripcion CLOB NOT NULL, medios CLOB NOT NULL, creado_en DATETIME NOT NULL, actualizado_en DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C9FA1603989D9B62 ON vehiculo (slug)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE consulta');
        $this->addSql('DROP TABLE usuario');
        $this->addSql('DROP TABLE vehiculo');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
