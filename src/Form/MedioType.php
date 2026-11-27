<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Una foto o un video del auto en el panel: se sube un archivo o se escribe una URL/ruta.
 * Los datos son un array {tipo, src, alt: {idioma: texto}, archivo}; "archivo" lo
 * procesa el controlador y no se guarda.
 */
final class MedioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tipo', ChoiceType::class, [
                'label' => 'admin.medio.tipo',
                'choices' => ['admin.medio.imagen' => 'imagen', 'admin.medio.video' => 'video'],
            ])
            ->add('archivo', FileType::class, [
                'label' => 'admin.medio.archivo',
                'required' => false,
                'constraints' => [
                    new Assert\File(
                        maxSize: '64M',
                        extensions: ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'mp4', 'webm'],
                        extensionsMessage: 'admin.medio.extension_invalida',
                        maxSizeMessage: 'admin.medio.muy_grande',
                    ),
                ],
            ])
            ->add('src', TextType::class, [
                'label' => 'admin.medio.src',
                'required' => false,
                'empty_data' => '',
                'help' => 'admin.medio.src_ayuda',
            ])
            ->add('credito', TextType::class, [
                'label' => 'admin.medio.credito',
                'required' => false,
                'empty_data' => '',
                'help' => 'admin.medio.credito_ayuda',
            ])
            ->add('alt', TraduccionesType::class, [
                'label' => 'admin.medio.alt',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
