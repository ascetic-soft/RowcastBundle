<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\Factory;

use AsceticSoft\RowcastSchema\Parser\AttributeSchemaParser;
use AsceticSoft\RowcastSchema\Parser\PhpSchemaParser;
use AsceticSoft\RowcastSchema\Parser\SchemaParserInterface;
use AsceticSoft\RowcastSchema\Parser\YamlSchemaParser;

final class SchemaParserFactory
{
    public function create(string $schemaPath): SchemaParserInterface
    {
        if (class_exists(AttributeSchemaParser::class) && is_dir($schemaPath)) {
            return new AttributeSchemaParser();
        }

        $extension = strtolower(pathinfo($schemaPath, PATHINFO_EXTENSION));

        return match ($extension) {
            'php' => new PhpSchemaParser(),
            'yaml', 'yml' => new YamlSchemaParser(),
            default => throw new \InvalidArgumentException(\sprintf(
                'Unsupported schema source "%s". Use a directory for attributes, or a .php/.yaml/.yml schema file.',
                $schemaPath,
            )),
        };
    }
}
