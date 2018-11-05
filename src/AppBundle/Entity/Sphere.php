<?php

namespace AppBundle\Entity;

class Sphere implements \Gedmo\Translatable\Translatable {
    /**
     * @var integer
     */
    private $id;
    /**
     * @var string
     */
    private $code;
    /**
     * @var string
     */
    private $name;
    /**
     * @var boolean
     */
    private $is_primary;
    /**
     * @var string
     */
    private $octgnid;
    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $cards;

    /**
     * Constructor
     */
    public function __construct() {
        $this->cards = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set code
     *
     * @param string $code
     *
     * @return Sphere
     */
    public function setCode($code) {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code
     *
     * @return string
     */
    public function getCode() {
        return $this->code;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Sphere
     */
    public function setName($name) {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Set isPrimary
     *
     * @param boolean $isPrimary
     *
     * @return Sphere
     */
    public function setIsPrimary($isPrimary) {
        $this->is_primary = $isPrimary;

        return $this;
    }

    /**
     * Get isPrimary
     *
     * @return boolean
     */
    public function getIsPrimary() {
        return $this->is_primary;
    }

    /**
     * Set octgnid
     *
     * @param string $octgnid
     *
     * @return Sphere
     */
    public function setOctgnid($octgnid) {
        $this->octgnid = $octgnid;

        return $this;
    }

    /**
     * Get octgnid
     *
     * @return string
     */
    public function getOctgnid() {
        return $this->octgnid;
    }

    /**
     * Add card
     *
     * @param \AppBundle\Entity\Card $card
     *
     * @return Sphere
     */
    public function addCard(\AppBundle\Entity\Card $card) {
        $this->cards[] = $card;

        return $this;
    }

    /**
     * Remove card
     *
     * @param \AppBundle\Entity\Card $card
     */
    public function removeCard(\AppBundle\Entity\Card $card) {
        $this->cards->removeElement($card);
    }

    /**
     * Get cards
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCards() {
        return $this->cards;
    }

    /*
    * I18N vars
    */
    private $locale = 'en';

    public function setTranslatableLocale($locale)
    {
        $this->locale = $locale;
    }
}
