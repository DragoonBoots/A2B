<?php


namespace DragoonBoots\A2B\Tests\Drivers;


use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Finder\Finder;

/**
 * Mock the Symfony Finder's complicated interface.
 */
trait FinderTestTrait
{

    /**
     * Create the fluent interface for the finder
     *
     * @param Finder|MockObject $finder
     * @param array             $finderMethodBlacklist
     *   A list of method names not to mock.
     *
     * @return MockObject
     * @throws \ReflectionException
     */
    protected function buildFinderMock(MockObject $finder, $finderMethodBlacklist = [])
    {
        if (empty($finderMethodBlacklist)) {
            $finderMethodBlacklist = [
                '__construct',
                'getIterator',
                'hasResults',
                'count',
            ];
        }
        foreach ((new \ReflectionClass(Finder::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if (!$reflectionMethod->isStatic() && !in_array($reflectionMethod->getName(), $finderMethodBlacklist)) {
                $finder->method($reflectionMethod->getName())
                    ->willReturnSelf();
            }
        }

        return $finder;
    }
}
