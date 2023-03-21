<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAnnotation;

// Doctrine
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait UTMTrait
 */
trait UTMTrait
{
    /**
     * johns-blog, google, facebook, newsletter, youtube
     *
     * @var string|null
     *
     * @ORM\Column(name="source", type="string", length=255, nullable=true)
     */
    protected ?string $source = null;

    /**
     * organic, cpc, cpm, social, referrer, affiliate
     *
     * @var string|null
     *
     * @ORM\Column(name="medium", type="string", length=255, nullable=true)
     */
    protected ?string $medium = null;

    /**
     * the title of the email campaign
     * the title of the paid ad
     *
     * @var string|null
     *
     * @ORM\Column(name="campaign", type="string", length=255, nullable=true)
     */
    protected ?string $campaign = null;

    /**
     * title of the video
     * title of the blog post
     * subject of the email
     *
     * @var string|null
     *
     * @ORM\Column(name="term", type="string", length=255, nullable=true)
     */
    protected ?string $term = null;

    /**
     * @var string|null
     *
     * @ORM\Column(name="content", type="string", length=255, nullable=true)
     */
    protected ?string $content = null;

    /**
     * @return string|null
     */
    public function getSource(): ?string
    {
        return $this->source;
    }

    /**
     * @param string|null $source
     * @return $this
     */
    public function setSource(?string $source): self
    {
        $this->source = $source;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getMedium(): ?string
    {
        return $this->medium;
    }

    /**
     * @param string|null $medium
     * @return $this
     */
    public function setMedium(?string $medium): self
    {
        $this->medium = $medium;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getCampaign(): ?string
    {
        return $this->campaign;
    }

    /**
     * @param string|null $campaign
     * @return $this
     */
    public function setCampaign(?string $campaign): self
    {
        $this->campaign = $campaign;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTerm(): ?string
    {
        return $this->term;
    }

    /**
     * @param string|null $term
     * @return $this
     */
    public function setTerm(?string $term): self
    {
        $this->term = $term;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * @param string|null $content
     * @return $this
     */
    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }
}
