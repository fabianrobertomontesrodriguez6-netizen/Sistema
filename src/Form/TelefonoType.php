<?php

declare(strict_types=1);

namespace App\Form;

use App\Validator\TelefonoInternacionalValidator;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Teléfono en dos partes: país (con su prefijo, "Argentina (+54)") y número.
 * Lo junta en un solo texto E.164 ("+541155551234"), que es lo que se guarda;
 * la validez la comprueba App\Validator\TelefonoInternacional en la entidad.
 * Si el número ya se escribe con "+", manda ese prefijo y no hace falta elegir país.
 */
final class TelefonoType extends AbstractType
{
    public function __construct(private readonly LocaleSwitcher $localeSwitcher)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $util = PhoneNumberUtil::getInstance();
        $paises = $this->paises($util);

        $builder
            ->add('pais', ChoiceType::class, [
                'label' => 'consulta.telefono_pais',
                'choices' => $paises,
                'choice_translation_domain' => false,
                'placeholder' => 'consulta.telefono_pais_elegir',
                'required' => false,
                // Número de ejemplo del país, para el placeholder (telefono_controller.js).
                'choice_attr' => fn (string $region) => ['data-ejemplo' => $this->ejemplo($util, $region)],
                'attr' => ['autocomplete' => 'tel-country-code', 'data-telefono-target' => 'pais', 'data-action' => 'telefono#actualizar'],
            ])
            ->add('numero', TelType::class, [
                'label' => 'consulta.telefono_numero',
                'required' => false,
                'attr' => ['autocomplete' => 'tel-national', 'inputmode' => 'tel', 'maxlength' => 30, 'data-telefono-target' => 'numero'],
            ]);

        $builder->addModelTransformer(new CallbackTransformer(
            // Lo guardado (E.164) → país + número en el formato del país.
            static function (?string $telefono) use ($util): array {
                if (null === $telefono || '' === $telefono) {
                    return ['pais' => null, 'numero' => null];
                }
                try {
                    $numero = $util->parse($telefono);
                    $region = $util->getRegionCodeForNumber($numero);
                    if (null !== $region && 'ZZ' !== $region) {
                        return ['pais' => $region, 'numero' => $util->format($numero, PhoneNumberFormat::NATIONAL)];
                    }
                } catch (NumberParseException) {
                }

                return ['pais' => null, 'numero' => $telefono]; // dato antiguo sin formato: tal cual
            },
            // País + número → E.164. Si no se puede interpretar, se deja lo escrito y el validador explica el error.
            static function (?array $datos) use ($util): ?string {
                $numero = trim((string) ($datos['numero'] ?? ''));
                if ('' === $numero) {
                    return null;
                }
                $region = $datos['pais'] ?? null;
                if (!preg_match(TelefonoInternacionalValidator::CARACTERES, $numero)
                    || (null === $region && !str_starts_with($numero, '+'))) {
                    return $numero;
                }
                try {
                    return $util->format($util->parse($numero, $region), PhoneNumberFormat::E164);
                } catch (NumberParseException) {
                    return $numero;
                }
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'consulta.telefono',
            'required' => false,
            'error_bubbling' => false,
        ]);
    }

    /**
     * Países con prefijo, por nombre en el idioma de la página: ["Argentina (+54)" => "AR", ...].
     *
     * @return array<string, string>
     */
    private function paises(PhoneNumberUtil $util): array
    {
        $idioma = $this->localeSwitcher->getLocale();
        $paises = [];
        foreach ($util->getSupportedRegions() as $region) {
            $nombre = Countries::exists($region) ? Countries::getName($region, $idioma) : $region;
            $paises[\sprintf('%s (+%d)', $nombre, $util->getCountryCodeForRegion($region))] = $region;
        }
        $orden = new \Collator($idioma);
        uksort($paises, static fn (string $a, string $b): int => (int) $orden->compare($a, $b));

        return $paises;
    }

    private function ejemplo(PhoneNumberUtil $util, string $region): string
    {
        $numero = $util->getExampleNumberForType($region, PhoneNumberType::MOBILE) ?? $util->getExampleNumber($region);

        return null === $numero ? '' : $util->format($numero, PhoneNumberFormat::NATIONAL);
    }
}
