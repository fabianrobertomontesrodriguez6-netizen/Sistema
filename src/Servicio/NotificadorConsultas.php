<?php

declare(strict_types=1);

namespace App\Servicio;

use App\Entity\Consulta;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Avisa por correo al concesionario de una consulta nueva.
 * Si el correo falla, la consulta ya quedó guardada y se ve en el panel: solo se anota en el log.
 * Con MAILER_DSN=null://null (por defecto) el correo se descarta.
 */
final class NotificadorConsultas
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(CONCESIONARIO_EMAIL)%')]
        private readonly string $emailConcesionario,
    ) {
    }

    public function avisar(Consulta $consulta): bool
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->emailConcesionario, 'AutoPlaza'))
            ->to($this->emailConcesionario)
            ->replyTo(new Address($consulta->getEmail(), $consulta->getNombre()))
            ->subject(\sprintf('Me interesa: %s', $consulta->getAutoNombre()))
            ->htmlTemplate('email/consulta.html.twig')
            ->context(['consulta' => $consulta]);

        try {
            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('No se pudo enviar el correo de la consulta {id}: {error}', [
                'id' => $consulta->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
