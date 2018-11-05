<?php 

namespace AppBundle\Repository;

class CycleRepository extends TranslatableRepository
{
	function __construct($entityManager)
	{
		parent::__construct($entityManager, $entityManager->getClassMetadata('AppBundle\Entity\Cycle'));
	}

	public function findAll()
	{
		$qb = $this->createQueryBuilder('c')
			->select('c')
			->orderBy('c.position', 'ASC');

		return $this->getResult($qb);
	}

	public function findAllWithPacks()
	{
		$qb = $this->createQueryBuilder('c')
			->select('c, p')
			->join('c.packs', 'p')
			->orderBy('c.position', 'ASC');

		return $this->getResult($qb);
	}
}
