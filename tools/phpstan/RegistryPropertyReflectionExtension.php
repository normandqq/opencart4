<?php

namespace Tools\PHPStan;

use Opencart\System\Engine\Registry;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;

class RegistryPropertyReflectionExtension implements PropertiesClassReflectionExtension {
	public function hasProperty(ClassReflection $classReflection, string $propertyName): bool {
		if (!$classReflection->is(Registry::class)) {
			return false;
		}

		return preg_match('/^(controller|model)_(.+)$/', $propertyName, $matches) === 1;
	}

	public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection {
		preg_match('/^(controller|model)_(.+)$/', $propertyName, $matches);
		$classType = $this->convertSnakeToStudly($matches[1]);
		$commonName = $this->convertSnakeToStudly($matches[2]);

		$broker = Broker::getInstance();

		$type = null;
		foreach (['Admin', 'Catalog'] as $domain) {
			$className1 = '\\Opencart\\' . $domain . '\\' . $classType . '\\' . $commonName;
			$className2 = preg_replace('/\\\\(?=[^\\\\]+$)/', '', $className1, 1);
			foreach ([$className1, $className2] as $className) {
				if ($broker->hasClass($className)) {
					$found = new ObjectType($className);
					if ($classType === 'Model') {
						$found = new GenericObjectType('\Opencart\System\Engine\Proxy', [$found]);
					}
					$type = $type ? TypeCombinator::union($type, $found) : $found;
				}
			}
		}
		if ($type) {
			$type = TypeCombinator::addNull($type);
		} else {
			$type = new NullType();
		}

		return new LoadedProperty($classReflection, $type);
	}

	private function convertSnakeToStudly(string $value): string {
		return str_replace(' ', '\\', ucwords(str_replace('_', ' ', $value)));
	}
}
