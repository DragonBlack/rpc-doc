<?php

declare(strict_types=1);

namespace DragonBlack\Bundle\JsonRpcDocBundle\Service;

class StructureBuilder
{
    private const MAX_RECURSION_DEEP = 2;

    private array $collection = [];

    /**
     * @template T
     * @param T $object
     * @return void
     */
    public function add($resolver): self
    {
        if (!is_object($resolver)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Argument of "%s" must be a type "object" but "%s" type given',
                    __METHOD__,
                    gettype($resolver)
                )
            );
        }

        $classReflection = new \ReflectionClass($resolver);
        $namespace = lcfirst($classReflection->getShortName());
        $methods = $classReflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        if (empty($methods)) {
            return $this;
        }

        if (!isset($this->collection[$namespace])) {
            $this->collection[$namespace] = [];
        }

        d($classReflection->getName());
        foreach ($methods as $method) {
            if ($method->isConstructor() || stripos($method->getShortName(), '__') === 0) {
                continue;
            }
            $this->collection[$namespace][$method->getShortName()] = [
                'params' => $this->parseAttributes($method),
                'result' => $this->parseReturnType($method),
            ];
        }
        
        return $this;
    }
    
    public function getAll(): array
    {
        return $this->collection;
    }

    private function parseAttributes(\ReflectionMethod $method)
    {
        $arguments = $method->getParameters();
        if (empty($arguments)) {
            return [];
        }

        $result = [];

        foreach($arguments as $argument) {
            if ($argument->isArray() || !$argument->getType()) {
                $result[$argument->getName()][] = (new DocCommentParser($method->getDocComment()))->getArgumentDefinition($argument);
                continue;
            }

            $type = $argument->getType()->getName();

            if (class_exists($type)) {
                $type = $this->parseObjectType($type);
            }

            $result[$argument->getName()] = [
                'type' => $type,
                'required' => !$argument->isOptional(),
                'allowNull' => $argument->allowsNull(),
            ];

            if ($argument->isOptional()) {
                $result[$argument->getName()]['default'] = $argument->getDefaultValue();
            }
        }

        return $result;
    }

    private function parseReturnType(\ReflectionMethod $method)
    {
        if (!$method->hasReturnType())
        {
            return 'void';
        }
        
        $type = $method->getReturnType();

        $result = [
            'type' => '',
            'allowNull' => $type->allowsNull(),
        ];

        if (class_exists($type->getName())) {
            $result['type'] = $this->parseObjectType($type->getName());
        } elseif ($type->getName() === 'array') {
            $result['type'] = (new DocCommentParser($method->getDocComment()))->getReturnDefinition($method);
        } else {
            $result['type'] = $type->getName();
        }

        return $result;
    }

    private function parseObjectType(string $className, int $deep = 1)
    {
        $class = new \ReflectionClass($className);

        $properties = $class->getProperties();

        $result = [];

        foreach($properties as $property) {
            if ($property->isPublic()) {
                if (!$property->getType()) {
                    $result[$property->getName()] = (new DocCommentParser($property->getDocComment()))->getPropertyDefinition();
                } else {
                    $type = $property->getType()->getName();
                    if ($deep <= self::MAX_RECURSION_DEEP && class_exists($type)) {
                        $type = $this->parseObjectType($type, $deep+1);
                    }

                    $result[$property->getName()] = $type;
                }
                continue;
            }

            $methodName = 'get' . ucfirst($property->getName());

            $method = $class->getMethod($methodName);
            if (!$method || !$method->isPublic()) {
                continue;
            }

            if (!$method->hasReturnType()) {
                $result[$property->getName()] = (new DocCommentParser($method->getDocComment()))->getReturnDefinition();
            } else {
                $type = $method->getReturnType()->getName();
                if ($deep <= self::MAX_RECURSION_DEEP && class_exists($type)) {
                    $type = $this->parseObjectType($type, $deep+1);
                }
                $result[$property->getName()] = $type;
            }
        }

        return $result;
    }
}
