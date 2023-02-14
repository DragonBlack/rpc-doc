<?php

declare(strict_types=1);

namespace DragonBlack\Bundle\JsonRpcDocBundle\Service;

class DocCommentParser
{
    private const ARGUMENT_TYPE_TEMPLATE = '/@param\s+(.*)\s+\S%s/';
    private const PROPERTY_TYPE_TEMPLATE = '/@var\s+(.*)\s*/';
    private const RETURN_TYPE_TEMPLATE = '/@return\s+(.*)\s+.*/';

    private string $docComment;

    public function __construct(?string $docComment)
    {
        $this->docComment = $docComment ?: '';
    }

    public function getArgumentDefinition(\ReflectionParameter $argument)
    {
        $pattern = sprintf(self::ARGUMENT_TYPE_TEMPLATE, $argument->getName());
        $matches = [];
        preg_match($pattern, $this->docComment, $matches);

        if (empty($matches[1])) {
            $result = [
                'type' => $argument->isArray() ? 'mixed[]' : 'mixed',
                'required' => !$argument->isOptional(),
                'allowNull' => true,
            ];

            if ($argument->isDefaultValueAvailable()) {
                $result['default'] = $argument->getDefaultValue();
            }

            return $result;
        }

        return [
            'type' => $matches[1],
            'required' => !$argument->isOptional(),
            'allowNull' => true,
        ];
    }

    public function getPropertyDefinition(\ReflectionProperty $property)
    {
        $type = preg_replace(self::PROPERTY_TYPE_TEMPLATE, '$1', $this->docComment);
        d($this->docComment, $type);

        return $type ?: 'mixed|null';
    }

    public function getReturnDefinition(\ReflectionMethod $method)
    {
        $type = preg_replace(self::RETURN_TYPE_TEMPLATE, '$1', $this->docComment);
        d($this->docComment, $type);

        return $type ?: 'mixed|null';
    }
}
