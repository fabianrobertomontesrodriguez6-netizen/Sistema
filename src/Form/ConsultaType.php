<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Consulta;
use App\Validator\NombrePersona;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulario "Me interesa" del detalle de un auto.
 */
final class ConsultaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'consulta.nombre',
                // Sin trim: así se rechaza "  Fabian" en lugar de corregirlo en silencio.
                'trim' => false,
                'constraints' => [new NombrePersona()],
                'attr' => ['autocomplete' => 'name'],
            ])
            ->add('email', EmailType::class, ['label' => 'consulta.email', 'invalid_message' => 'campo.email_invalido', 'attr' => ['autocomplete' => 'email']])
            ->add('telefono', TelefonoType::class)
            ->add('mensaje', TextareaType::class, ['label' => 'consulta.mensaje', 'attr' => ['rows' => 4]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Consulta::class]);
    }
}
