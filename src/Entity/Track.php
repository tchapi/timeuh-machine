<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TrackRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackRepository::class)]
#[ORM\Table(name: 'track')]
class Track
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $title;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $album = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $artist = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $startedAt;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $tuneefyLink = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $spotifyLink = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $deezerLink = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $valid = null;

    /**
     * Cleans up the track / artist / album separation of this Track.
     */
    public function clean(): void
    {
        if ('' === $this->title
            && null !== $this->artist
            && preg_match('/(?P<artist>[^\-\—]*)\s+[\-\–]\s+(?P<title>[^\-\—]*)/u', $this->artist, $matches)
        ) {
            $this->artist = $matches['artist'];
            $this->title = $matches['title'];
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setAlbum(?string $album): static
    {
        $this->album = $album;

        return $this;
    }

    public function getAlbum(): ?string
    {
        return $this->album;
    }

    public function setArtist(?string $artist): static
    {
        $this->artist = $artist;

        return $this;
    }

    public function getArtist(): ?string
    {
        return $this->artist;
    }

    public function setStartedAt(\DateTime $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getStartedAt(): \DateTime
    {
        return $this->startedAt;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setTuneefyLink(?string $tuneefyLink): static
    {
        $this->tuneefyLink = $tuneefyLink;

        return $this;
    }

    public function getTuneefyLink(): ?string
    {
        return $this->tuneefyLink;
    }

    public function setValid(bool $valid): static
    {
        $this->valid = $valid;

        return $this;
    }

    public function getValid(): ?bool
    {
        return $this->valid;
    }

    public function isValid(): ?bool
    {
        return $this->valid;
    }

    public function setSpotifyLink(?string $spotifyLink): static
    {
        $this->spotifyLink = $spotifyLink;

        return $this;
    }

    public function getSpotifyLink(): ?string
    {
        return $this->spotifyLink;
    }

    public function setDeezerLink(?string $deezerLink): static
    {
        $this->deezerLink = $deezerLink;

        return $this;
    }

    public function getDeezerLink(): ?string
    {
        return $this->deezerLink;
    }
}
