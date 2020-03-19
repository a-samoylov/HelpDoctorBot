<?php

declare(strict_types=1);

namespace App\Model\Telegram;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use TgBotApi\BotApiBase\BotApiRequest;
use TgBotApi\BotApiBase\BotApiRequestInterface;
use TgBotApi\BotApiBase\Normalizer\AnswerInlineQueryNormalizer;
use TgBotApi\BotApiBase\Normalizer\EditMessageResponseNormalizer;
use TgBotApi\BotApiBase\Normalizer\InputFileNormalizer;
use TgBotApi\BotApiBase\Normalizer\InputMediaNormalizer;
use TgBotApi\BotApiBase\Normalizer\JsonSerializableNormalizer;
use TgBotApi\BotApiBase\Normalizer\LegacyObjectNormalizerWrapper;
use TgBotApi\BotApiBase\Normalizer\MediaGroupNormalizer;
use TgBotApi\BotApiBase\Normalizer\PollNormalizer;
use TgBotApi\BotApiBase\Normalizer\UserProfilePhotosNormalizer;
use TgBotApi\BotApiBase\NormalizerInterface;

class BotApiNormalizer implements NormalizerInterface
{
    /**
     * @param $data
     * @param $type
     *
     * @throws ExceptionInterface
     *
     * @return object|array|bool
     */
    public function denormalize($data, $type)
    {
        $normalizer = new ObjectNormalizer(
            null,
            new CamelCaseToSnakeCaseNameConverter(),
            null,
            new PhpDocExtractor()
        );
        $arrayNormalizer = new ArrayDenormalizer();
        $dateNormalizer = new DateTimeNormalizer();
        $serializer = new Serializer([
            new UserProfilePhotosNormalizer($normalizer, $arrayNormalizer),
            new EditMessageResponseNormalizer($normalizer, $arrayNormalizer, $dateNormalizer),
            new DateTimeNormalizer(),
            $dateNormalizer,
            $normalizer,
            $arrayNormalizer,
        ]);

        return $serializer->denormalize($data, $type, null, []);
    }

    /**
     * @param $method
     *
     * @throws ExceptionInterface
     */
    public function normalize($method): BotApiRequestInterface
    {
        $isLegacy = !\defined(AbstractObjectNormalizer::class . '::SKIP_NULL_VALUES');

        $files = [];

        $objectNormalizer = new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
        if ($isLegacy) {
            $objectNormalizer = new LegacyObjectNormalizerWrapper($objectNormalizer);
        }

        $serializer = new Serializer([
            new PollNormalizer($objectNormalizer),
            new InputFileNormalizer($files),
            new MediaGroupNormalizer(new InputMediaNormalizer($objectNormalizer, $files), $objectNormalizer),
            new JsonSerializableNormalizer($objectNormalizer),
            new AnswerInlineQueryNormalizer($objectNormalizer),
            new DateTimeNormalizer(),
            $objectNormalizer,
        ]);

        $data = $serializer->normalize(
            $method,
            null,
            [
                'skip_null_values' => true,
            ]
        );

        return new BotApiRequest($data, $files);
    }
}
