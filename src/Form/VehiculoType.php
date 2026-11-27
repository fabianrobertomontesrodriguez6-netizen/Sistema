<?php

declare(strict_types=1);

namespace App\Form;

use App\Catalogo\Valores;
use App\Entity\Vehiculo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Alta y edición de un auto en el panel de administración.
 */
final class VehiculoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $opciones = static fn (array $valores): array => array_combine($valores, $valores);

        $builder
            ->add('marca', TextType::class, ['label' => 'admin.auto.marca'])
            ->add('modelo', TextType::class, ['label' => 'admin.auto.modelo'])
            ->add('version', TextType::class, ['label' => 'admin.auto.version', 'required' => false, 'help' => 'admin.auto.version_ayuda'])
            ->add('anio', IntegerType::class, ['label' => 'auto.anio'])
            ->add('slug', TextType::class, ['label' => 'admin.auto.slug', 'help' => 'admin.auto.slug_ayuda', 'required' => false])
            ->add('precio', IntegerType::class, ['label' => 'admin.auto.precio'])
            ->add('kilometraje', IntegerType::class, ['label' => 'admin.auto.kilometraje'])
            ->add('combustible', ChoiceType::class, ['label' => 'auto.combustible', 'choices' => $opciones(Valores::COMBUSTIBLES), 'choice_translation_domain' => 'valores'])
            ->add('transmision', ChoiceType::class, ['label' => 'auto.transmision', 'choices' => $opciones(Valores::TRANSMISIONES), 'choice_translation_domain' => 'valores'])
            ->add('carroceria', ChoiceType::class, ['label' => 'auto.carroceria', 'choices' => $opciones(Valores::CARROCERIAS), 'choice_translation_domain' => 'valores'])
            ->add('motor', TextType::class, ['label' => 'auto.motor', 'required' => false, 'help' => 'admin.auto.motor_ayuda'])
            ->add('potencia', IntegerType::class, ['label' => 'admin.auto.potencia', 'required' => false])
            ->add('torque', IntegerType::class, ['label' => 'admin.auto.torque', 'required' => false])
            ->add('traccion', ChoiceType::class, ['label' => 'auto.traccion', 'required' => false, 'choices' => $opciones(Valores::TRACCIONES), 'choice_translation_domain' => 'valores', 'placeholder' => '—'])
            ->add('aceleracion', NumberType::class, ['label' => 'admin.auto.aceleracion', 'required' => false, 'scale' => 1, 'html5' => true, 'attr' => ['step' => '0.1']])
            ->add('velocidadMaxima', IntegerType::class, ['label' => 'admin.auto.velocidad_maxima', 'required' => false])
            ->add('descripcion', TraduccionesType::class, [
                'label' => 'admin.auto.descripcion',
                'multilinea' => true,
                'espanol_obligatorio' => true,
            ])
            ->add('medios', CollectionType::class, [
                'label' => 'admin.auto.medios',
                'entry_type' => MedioType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => static fn (?array $medio): bool => null === $medio || ('' === ($medio['src'] ?? '') && null === ($medio['archivo'] ?? null)),
                'prototype' => true,
            ]);

        // Sin slug escrito se genera uno con marca, modelo y año: "ford-mustang-gt-2022".
        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $evento): void {
            $vehiculo = $evento->getData();
            if ($vehiculo instanceof Vehiculo && '' === $vehiculo->getSlug()) {
                $vehiculo->setSlug((new AsciiSlugger())->slug($vehiculo->getNombre())->lower()->toString());
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Vehiculo::class]);
    }
}
