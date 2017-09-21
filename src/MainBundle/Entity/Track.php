<?php

namespace MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="MainBundle\Repository\TrackRepository")
 * @ORM\Table(name="track")
 */
class Track
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    private $title;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $album;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $artist;

    /**
     * @ORM\Column(type="datetime")
     */
    private $startedAt;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $image;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $tuneefyLink;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $spotifyLink;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $valid;

    /**
     * Cleans up the track / artist / album separation of this Track.
     */
    public function clean(): void
    {
        if (null === $this->title &&
            preg_match("(?P<artist>[^\-\—]*)\s+[\-\–]\s+(?P<title>[^\-\—]*)", $this->artist, $matches)
            ) {
            $this->artist = $matches['artist'];
            $this->title = $matches['title'];
        }
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set title.
     *
     * @param string $title
     *
     * @return Track
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set album.
     *
     * @param string $album
     *
     * @return Track
     */
    public function setAlbum($album)
    {
        $this->album = $album;

        return $this;
    }

    /**
     * Get album.
     *
     * @return string
     */
    public function getAlbum()
    {
        return $this->album;
    }

    /**
     * Set artist.
     *
     * @param string $artist
     *
     * @return Track
     */
    public function setArtist($artist)
    {
        $this->artist = $artist;

        return $this;
    }

    /**
     * Get artist.
     *
     * @return string
     */
    public function getArtist()
    {
        return $this->artist;
    }

    /**
     * Set startedAt.
     *
     * @param \DateTime $startedAt
     *
     * @return Track
     */
    public function setStartedAt($startedAt)
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    /**
     * Get startedAt.
     *
     * @return \DateTime
     */
    public function getStartedAt()
    {
        return $this->startedAt;
    }

    /**
     * Set image.
     *
     * @param string $image
     *
     * @return Track
     */
    public function setImage($image)
    {
        $this->image = $image;

        return $this;
    }

    /**
     * Get image.
     *
     * @return string
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * Set tuneefyLink.
     *
     * @param string $tuneefyLink
     *
     * @return Track
     */
    public function setTuneefyLink($tuneefyLink)
    {
        $this->tuneefyLink = $tuneefyLink;

        return $this;
    }

    /**
     * Get tuneefyLink.
     *
     * @return string
     */
    public function getTuneefyLink()
    {
        return $this->tuneefyLink;
    }

    /**
     * Set valid.
     *
     * @param bool $valid
     *
     * @return Track
     */
    public function setValid($valid)
    {
        $this->valid = $valid;

        return $this;
    }

    /**
     * Get valid.
     *
     * @return bool
     */
    public function getValid()
    {
        return $this->valid;
    }

    /**
     * Is valid.
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->valid;
    }

    /**
     * Set spotifyLink.
     *
     * @param string $spotifyLink
     *
     * @return Track
     */
    public function setSpotifyLink($spotifyLink)
    {
        $this->spotifyLink = $spotifyLink;

        return $this;
    }

    /**
     * Get spotifyLink.
     *
     * @return string
     */
    public function getSpotifyLink()
    {
        return $this->spotifyLink;
    }
}
