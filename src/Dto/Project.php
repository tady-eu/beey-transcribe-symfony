<?php declare(strict_types = 1);

namespace TadyEu\BeeyTranscriber\Dto;

use TadyEu\BeeyTranscriber\Enums\ProcessingState;
use DateTimeImmutable;
use function is_array;
use const DATE_ATOM;

class Project
{
    private int $_id;
    private int $_accessToken;
    private ?DateTimeImmutable $_created = null;
    private ?int $_creatorId = null;
    private ?DateTimeImmutable $_deleteAt = null;

    /**
     * @var array<int, array{'Author'?:string,'Name'?:string,'Notes'?:string,'Start'?:string}>|null
     */
    private ?array $_description = null;

    private ?DateTimeImmutable $_expirationDate = null;
    private bool $_isReadOnly = false;
    private bool $_isTeamProject = false;

    /**
     * @var array<int, array{'Category'?:string,'Highlight'?:string,'Text'?:string,'TimestampMs'?:int,'Type'?:string}>
     */
    private array $_keywordsHighlight = [];

    private string $_length = '';

    /**
     * @var array<int, array{'HasVideo'?:bool,'IsPackaged'?:bool}>|null
     */
    private ?array $_mediaInfo = null;

    private ProcessingState $_processingState = ProcessingState::None;
    private int $_shareCount = 0;

    /**
     * @var string[]
     */
    private array $_tags = [];

    /**
     * @var mixed
     * Transcription parameters used during processing
     */
    private mixed $_transcriptionConfig = null;

    private ?DateTimeImmutable $_updated = null;

    /**
     * Creates a Project from a Beey API response.
     *
     * @param array<string,mixed> $apiResponse
     */
    public static function fromApi(array $apiResponse): self
    {
        $data = $apiResponse['Data'] ?? $apiResponse;

        $p = new self();

        $p->_id = isset($data['Id']) ? (int) $data['Id'] : 0;
        $p->_accessToken = isset($data['AccessToken']) ? (int) $data['AccessToken'] : 0;

        $p->_created = isset($data['Created']) && $data['Created'] !== '' ? new DateTimeImmutable($data['Created']) : null;
        $p->_creatorId = isset($data['CreatorId']) ? (int) $data['CreatorId'] : null;
        $p->_deleteAt = isset($data['DeleteAt']) && $data['DeleteAt'] !== '' ? new DateTimeImmutable($data['DeleteAt']) : null;

        $p->_description = isset($data['Description']) && is_array($data['Description']) ? $data['Description'] : null;

        $p->_expirationDate = isset($data['ExpirationDate']) && $data['ExpirationDate'] !== '' ? new DateTimeImmutable($data['ExpirationDate']) : null;

        $p->_isReadOnly = isset($data['IsReadOnly']) ? (bool) $data['IsReadOnly'] : false;
        $p->_isTeamProject = isset($data['IsTeamProject']) ? (bool) $data['IsTeamProject'] : false;

        $p->_keywordsHighlight = isset($data['KeywordsHighlight']) && is_array($data['KeywordsHighlight']) ? $data['KeywordsHighlight'] : [];

        $p->_length = isset($data['Length']) ? (string) $data['Length'] : '';

        $p->_mediaInfo = isset($data['MediaInfo']) && is_array($data['MediaInfo']) ? $data['MediaInfo'] : null;

        $p->_processingState = isset($data['ProcessingState']) ? ProcessingState::from($data['ProcessingState']) : ProcessingState::None;
        $p->_shareCount = isset($data['ShareCount']) ? (int) $data['ShareCount'] : 0;
        $p->_tags = isset($data['Tags']) && is_array($data['Tags']) ? $data['Tags'] : [];
        $p->_transcriptionConfig = $data['TranscriptionConfig'] ?? null;
        $p->_updated = isset($data['Updated']) && $data['Updated'] !== '' ? new DateTimeImmutable($data['Updated']) : null;

        return $p;
    }

    public function getId(): int
    {
        return $this->_id;
    }

    public function getAccessToken(): int
    {
        return $this->_accessToken;
    }

    public function getCreated(): ?DateTimeImmutable
    {
        return $this->_created;
    }

    public function getCreatorId(): ?int
    {
        return $this->_creatorId;
    }

    public function getDeleteAt(): ?DateTimeImmutable
    {
        return $this->_deleteAt;
    }

    /**
     * @return array{'Author'?:string,'Name'?:string,'Notes'?:string,'Start'?:string}|null
     */
    public function getDescription(): ?array
    {
        return $this->_description;
    }

    public function getExpirationDate(): ?DateTimeImmutable
    {
        return $this->_expirationDate;
    }

    public function isReadOnly(): bool
    {
        return $this->_isReadOnly;
    }

    public function isTeamProject(): bool
    {
        return $this->_isTeamProject;
    }

    /**
     * @return array{'Category'?:string,'Highlight'?:string,'Text'?:string,'TimestampMs'?:int,'Type'?:string}
     */
    public function getKeywordsHighlight(): array
    {
        return $this->_keywordsHighlight;
    }

    public function getLength(): string
    {
        return $this->_length;
    }

    /**
     * @return array{'HasVideo'?:bool,'IsPackaged'?:bool}|null
     */
    public function getMediaInfo(): ?array
    {
        return $this->_mediaInfo;
    }

    public function getProcessingState(): ProcessingState
    {
        return $this->_processingState;
    }

    public function getShareCount(): int
    {
        return $this->_shareCount;
    }

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->_tags;
    }

    public function getTranscriptionConfig(): mixed
    {
        return $this->_transcriptionConfig;
    }

    public function getUpdated(): ?DateTimeImmutable
    {
        return $this->_updated;
    }

    /**
     * @return array{'Id':int,'AccessToken':int,'Created'?:string,'CreatorId'?:int,'DeleteAt'?:string,'Description'?:array{'Author'?:string,'Name'?:string,'Notes'?:string,'Start'?:string},'ExpirationDate'?:string,'IsReadOnly':bool,'IsTeamProject':bool,'KeywordsHighlight':array<array{'Category'?:string,'Highlight'?:string,'Text'?:string,'TimestampMs'?:int,'Type'?:string}),'Length':string,'MediaInfo'?:array{'HasVideo'?:bool,'IsPackaged'?:bool},'ProcessingState':string,'ShareCount':int,'Tags':array<string>,'TranscriptionConfig':mixed,'Updated'?:string}
     */
    public function toArray(): mixed
    {
        return [
            'Id' => $this->_id,
            'AccessToken' => $this->_accessToken,
            'Created' => $this->_created?->format(DATE_ATOM),
            'CreatorId' => $this->_creatorId,
            'DeleteAt' => $this->_deleteAt?->format(DATE_ATOM),
            'Description' => $this->_description,
            'ExpirationDate' => $this->_expirationDate?->format(DATE_ATOM),
            'IsReadOnly' => $this->_isReadOnly,
            'IsTeamProject' => $this->_isTeamProject,
            'KeywordsHighlight' => $this->_keywordsHighlight,
            'Length' => $this->_length,
            'MediaInfo' => $this->_mediaInfo,
            'ProcessingState' => $this->_processingState->value,
            'ShareCount' => $this->_shareCount,
            'Tags' => $this->_tags,
            'TranscriptionConfig' => $this->_transcriptionConfig,
            'Updated' => $this->_updated?->format(DATE_ATOM),
        ];
    }

}
