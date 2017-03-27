<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\Card;

class ExcelController extends Controller {
	public function downloadFormAction() {
		$em = $this->getDoctrine()->getManager();
		$packs = $em->getRepository('AppBundle:Pack')->findBy([], ['dateRelease' => 'ASC', 'name' => 'ASC']);

		return $this->render('AppBundle:Excel:download_form.html.twig', [
			'packs' => $packs
		]);
	}

	public function downloadProcessAction(Request $request) {
		$ignoredFields = ['id', 'dateCreation', 'dateUpdate'];

		$em = $this->getDoctrine()->getManager();

		$pack_id = $request->request->get('pack');
		if ($pack_id == 0) {
			$cards = $em->getRepository('AppBundle:Card')->findBy([], ['code' => 'ASC']);
			$pack_name = 'LotR LCG Cards';
		} else {
			$pack = $em->getRepository('AppBundle:Pack')->find($pack_id);
			$cards = $em->getRepository('AppBundle:Card')->findBy(['pack' => $pack], ['code' => 'ASC']);
			$pack_name = $pack->getName();
		}

		$fieldNames = $em->getClassMetadata('AppBundle:Card')->getFieldNames();

		$associationMappings = $em->getClassMetadata('AppBundle:Card')->getAssociationMappings();

		/* @var $card \AppBundle\Entity\Card */
		foreach ($cards as $card) {
			if (empty($lastModified) || $lastModified < $card->getDateUpdate()) {
				$lastModified = $card->getDateUpdate();
			}
		}

		$phpExcelObject = $this->get('phpexcel')->createPHPExcelObject();
		$phpExcelObject->getProperties()->setCreator("Sydtrack")->setLastModifiedBy($lastModified->format('Y-m-d'))->setTitle($pack_name);
		$phpActiveSheet = $phpExcelObject->setActiveSheetIndex(0);
		$phpActiveSheet->setTitle($pack_name);

		$col_index = 0;
		foreach ($associationMappings as $fieldName => $associationMapping) {
			if ($associationMapping['isOwningSide']) {
				$phpCell = $phpActiveSheet->getCellByColumnAndRow($col_index++, 1);
				$phpCell->setValue($fieldName);
			}
		}
		foreach ($fieldNames as $fieldName) {
			if (in_array($fieldName, $ignoredFields)) {
				continue;
			}
			$phpCell = $phpActiveSheet->getCellByColumnAndRow($col_index++, 1);
			$phpCell->setValue($fieldName);
		}

		foreach ($cards as $row_index => $card) {
			$col_index = 0;
			foreach ($associationMappings as $fieldName => $associationMapping) {
				if ($associationMapping['isOwningSide']) {
					$getter = str_replace(' ', '', ucwords(str_replace('_', ' ', "get_$fieldName")));
					$value = $card->$getter() ? $card->$getter()->getName() : '';

					$phpCell = $phpActiveSheet->getCellByColumnAndRow($col_index++, $row_index + 2);
					$phpCell->setValue($value);
				}
			}
			foreach ($fieldNames as $fieldName) {
				if (in_array($fieldName, $ignoredFields)) {
					continue;
				}

				$getter = str_replace(' ', '', ucwords(str_replace('_', ' ', "get_$fieldName")));
				$value = $card->$getter();
				if (!isset($value)) {
					$value = '';
				}
				$type = $em->getClassMetadata('AppBundle:Card')->getTypeOfField($fieldName);

				$phpCell = $phpActiveSheet->getCellByColumnAndRow($col_index++, $row_index + 2);
				if ($fieldName == 'code') {
					$phpCell->setValueExplicit($value, 's');
				} else {
					if ($type == 'boolean') {
						$phpCell->setValue($value ? "1" : "");
					} else {
						$phpCell->setValue($value);
					}
				}
			}
		}

		$writer = $this->get('phpexcel')->createWriter($phpExcelObject, 'Excel2007');
		$response = $this->get('phpexcel')->createStreamedResponse($writer);
		$response->headers->set('Content-Type', 'text/vnd.ms-excel; charset=utf-8');
		$response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $this->get('texts')->slugify($pack_name) . '.xlsx'));
		$response->headers->add(['Access-Control-Allow-Origin' => '*']);

		return $response;
	}

	public function uploadFormAction() {
		return $this->render('AppBundle:Excel:upload_form.html.twig');
	}

	public function uploadProcessAction(Request $request) {
		/* @var $uploadedFile \Symfony\Component\HttpFoundation\File\UploadedFile */
		$uploadedFile = $request->files->get('upfile');
		$inputFileName = $uploadedFile->getPathname();
		$inputFileType = \PHPExcel_IOFactory::identify($inputFileName);
		$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
		$objReader->setReadDataOnly(true);
		$objPHPExcel = $objReader->load($inputFileName);
		$objWorksheet = $objPHPExcel->getActiveSheet();

		$enableCardCreation = $request->request->has('create');

		// analysis of first row
		$colNames = [];

		$cards = [];
		$firstRow = true;
		foreach ($objWorksheet->getRowIterator() as $row) {
			// dismiss first row (titles)
			if ($firstRow) {
				$firstRow = false;

				// analysis of first row
				foreach ($row->getCellIterator() as $cell) {
					$colNames[$cell->getColumn()] = $cell->getValue();
				}
				continue;
			}

			$card = [];

			$cellIterator = $row->getCellIterator();
			foreach ($cellIterator as $cell) {
				$col = $cell->getColumn();
				$colName = $colNames[$col];

				//$setter = str_replace(' ', '', ucwords(str_replace('_', ' ', "set_$fieldName")));
				$card[$colName] = $cell->getValue();
			}
			if (count($card) && !empty($card['code'])) {
				$cards[] = $card;
			}
		}

		/* @var $em \Doctrine\ORM\EntityManager */
		$em = $this->getDoctrine()->getManager();
		$repo = $em->getRepository('AppBundle:Card');

		$metaData = $em->getClassMetadata('AppBundle:Card');
		$fieldNames = $metaData->getFieldNames();
		$associationMappings = $metaData->getAssociationMappings();

		$counter = 0;
		foreach ($cards as $card) {
			/* @var $entity \AppBundle\Entity\Card */
			$entity = $repo->findOneBy(['code' => $card['code']]);
			if (!$entity) {
				if ($enableCardCreation) {
					$entity = new Card();
					$now = new \DateTime();
					$entity->setDateCreation($now);
					$entity->setDateUpdate($now);
				} else {
					continue;
				}
			}

			$changed = false;
			$output = ["<h4>" . $card['name'] . "</h4>"];

			foreach ($card as $colName => $value) {
				$getter = str_replace(' ', '', ucwords(str_replace('_', ' ', "get_$colName")));
				$setter = str_replace(' ', '', ucwords(str_replace('_', ' ', "set_$colName")));

				if (key_exists($colName, $associationMappings)) {
					$associationMapping = $associationMappings[$colName];

					$associationRepository = $em->getRepository($associationMapping['targetEntity']);
					$associationEntity = $associationRepository->findOneBy(['name' => $value]);
					if (!$associationEntity) {
						throw new \Exception("cannot find entity [$colName] of name [$value]");
					}
					if (!$entity->$getter() || $entity->$getter()->getId() !== $associationEntity->getId()) {
						$changed = true;
						$output[] = "<p>association [$colName] changed</p>";

						$entity->$setter($associationEntity);
					}
				} else {
					if (in_array($colName, $fieldNames)) {
						$type = $metaData->getTypeOfField($colName);
						if ($type === 'boolean') {
							$value = (boolean)$value;
						}
						if ($entity->$getter() != $value || ($entity->$getter() === null && $entity->$getter() !== $value)) {
							$changed = true;
							$output[] = "<p>field [$colName] changed</p>";

							$entity->$setter($value);
						}
					}
				}
			}

			if ($changed) {
				$em->persist($entity);
				$counter++;

				echo join("", $output);
			}
		}

		$em->flush();

		return new Response($counter . " cards changed or added");
	}

	public function uploadTranslationFormAction() {
		return $this->render('AppBundle:Excel:upload_translation_form.html.twig');
	}

	public function uploadTranslationProcessAction(Request $request) {
		$locale = $request->get('locale');

		/* @var $uploadedFile \Symfony\Component\HttpFoundation\File\UploadedFile */
		$uploadedFile = $request->files->get('upfile');
		$inputFileName = $uploadedFile->getPathname();


		$em = $this->getDoctrine()->getManager();
		$excel = $this->get('phpexcel')->createPHPExcelObject($inputFileName);

		//$things = array('type', 'sphere', 'cycle', 'pack');

		$things = array(
			'type' => array(
				'count' => 0,
				'elems' => array()
			),
			'sphere' => array(
				'count' => 0,
				'elems' => array()
			),
			'cycle' => array(
				'count' => 0,
				'elems' => array()
			),
			'pack' => array(
				'count' => 0,
				'elems' => array()
			)
		);
		foreach($things as $thing => &$data) {
			$thingSheet = $excel->getSheetByName($thing."s");
			$entityName = 'AppBundle\\Entity\\'.ucfirst($thing);

			$first = TRUE;
			foreach($thingSheet->getRowIterator() as $row) {
				if($first) {
					$first = FALSE;
					continue;
				}

				$englishName = $thingSheet->getCellByColumnAndRow(0, $row->getRowIndex())->getValue();
				$translatedName = $thingSheet->getCellByColumnAndRow(1, $row->getRowIndex())->getValue();

				$entity = $em->getRepository($entityName)->findOneBy(['name' => $englishName]);
				if(!$entity) {
					continue;
				} else {
					$entity->setTranslatableLocale($locale);
					$entity->setName($translatedName);
					$em->persist($entity);
					$data['count']++;
					$data['elems'][] = "$englishName => $translatedName";
				}

				$em->flush();
			}
		}	

		$cardSheet = $excel->getSheetByName('cards');
		$first = TRUE;
		$cardCount = 0;
		$cardErrorCount = array();
		foreach($cardSheet->getRowIterator() as $row) {
			if($first) {
				$first = FALSE;
				continue;
			}

			$code = sprintf("%05d", $cardSheet->getCellByColumnAndRow(5, $row->getRowIndex())->getValue());
			$name = $cardSheet->getCellByColumnAndRow(6, $row->getRowIndex())->getValue();
			$traits = $cardSheet->getCellByColumnAndRow(7, $row->getRowIndex())->getValue();
			$text = $cardSheet->getCellByColumnAndRow(9, $row->getRowIndex())->getValue();
			$flavor = $cardSheet->getCellByColumnAndRow(10, $row->getRowIndex())->getValue();
			$card = $em->getRepository('AppBundle:Card')->findOneBy(['code' => $code]);
			if(!$card) {
				$cardErrorCount[] = $code;
				continue;
			} else {
				$card->setTranslatableLocale($locale);
				$card->setName($name);
				$card->setTraits($traits);
				$card->setText($text);
				$card->setFlavor($flavor);
				$em->persist($card);
				$cardCount++;
			}
		}

		$em->flush();

		$result = "Translation into '$locale' done.\n\n";
		foreach($things as $thing => $lastData) {
			$result = $result . "Result on ".ucfirst($thing).": \n\n";
			$result = $result . "   - Count: ".$lastData['count']."\n";
			$result = $result . "   - Elems: \n";
			foreach($lastData['elems'] as $elem) {
				$result = $result. "      - ".$elem."\n";
			}
			
		}
		$result = $result."\n\n\nResult on Card: $cardCount cards translated (Errors: ".join(", ", $cardErrorCount).").";

		$response = new Response($result);
		$response->headers->set('Content-Type', 'text/plain; charset=utf-8');

		return $response;
	}
}
