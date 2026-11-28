<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un campo por idioma ({es, en, pt}). El español puede ser obligatorio.
 */
final class TraduccionesType extends AbstractType
{
    /**
     * @param list<string> $idiomas
     */
    public function __construct(
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $idiomas,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ($this->idiomas as $idioma) {
            $obligatorio = 'es' === $idioma && $options['espanol_obligatorio'];
            $builder->add($idioma, $options['multilinea'] ? TextareaType::class : TextType::class, [
                'label' => strtoupper($idioma),
                'translation_domain' => false,
                'required' => $obligatorio,
                'empty_data' => '',
                'attr' => ['lang' => $idioma] + ($options['multilinea'] ? ['rows' => 3] : []),
                'constraints' => $obligatorio ? [new Assert\NotBlank(message: 'campo.obligatorio')] : [],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'multilinea' => false,
            'espanol_obligatorio' => false,
        ]);
        $resolver->setAllowedTypes('multilinea', 'bool');
        $resolver->setAllowedTypes('espanol_obligatorio', 'bool');
    }
}
