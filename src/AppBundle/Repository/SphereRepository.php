<?php 

namespace AppBundle\Repository;

class SphereRepository extends TranslatableRepository
{
	function __construct($entityManager)
	{
		parent::__construct($entityManager, $entityManager->getClassMetadata('AppBundle\Entity\Sphere'));
	}

	public function findAll()
	{
		$qb = $this->createQueryBuilder('s')
			->select('s')
			->orderBy('s.name', 'ASC');

		return $this->getResult($qb);
	}
}
