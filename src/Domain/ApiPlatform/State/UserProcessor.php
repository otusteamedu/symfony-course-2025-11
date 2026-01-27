<?php

namespace App\Domain\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
use App\Controller\Web\CreateUser\v2\Manager;
use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;

/**
 * @implements ProcessorInterface<CreateUserDTO, CreatedUserDTO>
 */
class UserProcessor implements ProcessorInterface
{
    public function __construct(private readonly Manager $manager)
    {
    }

    /**
     * @param CreateUserDTO $data
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return CreatedUserDTO
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CreatedUserDTO
    {
        return $this->manager->create($data);
    }
}