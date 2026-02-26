<?php declare(strict_types = 1);

namespace TadyEu\BeeyTranscriber;

use TadyEu\BeeyTranscriber\BeeyException;
use TadyEu\BeeyTranscriber\Dto\Project;
use DateTime;
use DateTimeInterface;
use Exception;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function array_filter;
use function array_merge;
use function fclose;
use function filesize;
use function fopen;
use function is_readable;
use function is_resource;

/**
 * Wrapper for Beey Transcriber API v2.
 *
 * @see https://docs.beey.io/v2/authentication.html
 */
class BeeyTranscriber
{

    public function __construct(private HttpClientInterface $beeyTranscriber)
    {
    }

    /**
     * Creates a new transcription project in Beey.
     *
     * @param string                 $name         Project name
     * @param string|null            $customPath   Optional custom path in Beey
     * @param DateTimeInterface|null $transcribeAt Date and time to start transcription
     * 
     * @throws BeeyException
     * @return Project Created project
     */
    public function addProject(
        string $name,
        ?string $customPath,
        ?DateTimeInterface $transcribeAt = null,
    ): Project {
        try {
            $data = $this->beeyTranscriber->request(
                'POST', 'projects', [
                'json' => array_filter(
                    [
                    'CustomPath' => $customPath,
                    'Name' => $name,
                    'Start' => $transcribeAt ? $transcribeAt->format(DateTime::ATOM) : null,
                    ], static fn($value) => $value !== null
                ),
                ]
            );
            if ($data->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $data->getStatusCode());
            }
            return Project::fromApi($data->toArray());
        } catch (Exception $e) {
            throw new BeeyException('Error adding project: ' . $e->getMessage());
        }
    }

    /**
     * Returns a project by ID.
     *
     * @param int $id Project ID
     * 
     * @throws BeeyException
     * @return Project|null Project or null if it does not exist
     */
    public function getProject(int $id): ?Project
    {
        try {
            $data = $this->beeyTranscriber->request('GET', 'projects/' . $id);
            if ($data->getStatusCode() === 404) {
                return null;
            }
            if ($data->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $data->getStatusCode());
            }

            return Project::fromApi($data->toArray());
        } catch (Exception $e) {
            throw new BeeyException('Error fetching project ' . $id . ': ' . $e->getMessage());
        }
    }

    /**
     * Deletes a project by ID.
     *
     * @param int $id Project ID
     * 
     * @throws BeeyException
     * @return Project Deleted project
     */
    public function deleteProject(int $id): Project
    {
        try {
            $data = $this->beeyTranscriber->request('DELETE', 'projects/' . $id);
            if ($data->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $data->getStatusCode());
            }
            return Project::fromApi($data->toArray());
        } catch (Exception $e) {
            throw new BeeyException('Error deleting project ' . $id . ': ' . $e->getMessage());
        }
    }

    /**
     * Uploads a media file to a project.
     *
     * @param int    $projectId Project ID
     * @param string $filePath  File path
     * 
     * @throws BeeyException
     * @return void
     */
    public function uploadMediaFile(int $projectId, string $filePath): void
    {
        $handle = null;
        try {
            if (!is_readable($filePath)) {
                throw new Exception('File not readable: ' . $filePath);
            }

            $size = filesize($filePath);
            if ($size === false) {
                throw new Exception('Could not determine file size for ' . $filePath);
            }

            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                throw new Exception('Could not open file: ' . $filePath);
            }

            $response = $this->beeyTranscriber->request(
                'POST', 'projects/' . $projectId . '/files/uploadmediafile', [
                'query' => [
                    'FileSize' => $size,
                ],
                'body' => [
                    'File' => $handle,
                ],
                ]
            );

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $response->getStatusCode());
            }
        } catch (Exception $e) {
            throw new BeeyException('Error uploading media file to project ' . $projectId . ': ' . $e->getMessage());
        } finally {
            if ($handle !== null && is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * Uploads a media file to a project by streaming it directly from an HTTP/HTTPS URL.
     *
     * The file is not stored on disk — data is streamed chunk by chunk from the source
     * URL directly into the Beey API upload request.
     *
     * The source server must include a Content-Length header in its response,
     * as the Beey API requires the file size upfront.
     *
     * @param int    $projectId Project ID
     * @param string $url       Publicly accessible HTTP/HTTPS URL of the media file
     *
     * @throws BeeyException
     * @return void
     */
    public function uploadMediaFileFromUrl(int $projectId, string $url): void
    {
        $externalClient = HttpClient::create();

        try {
            $downloadResponse = $externalClient->request('GET', $url);

            if ($downloadResponse->getStatusCode() !== 200) {
                throw new Exception('Failed to download file from URL, status: ' . $downloadResponse->getStatusCode());
            }

            $headers = $downloadResponse->getHeaders();
            if (!isset($headers['content-length'][0])) {
                throw new Exception('Source server did not provide a Content-Length header, cannot determine file size');
            }

            $fileSize = (int) $headers['content-length'][0];

            $uploadResponse = $this->beeyTranscriber->request(
                'POST', 'projects/' . $projectId . '/files/uploadmediafile', [
                'query' => [
                    'FileSize' => $fileSize,
                ],
                'body' => [
                    'File' => StreamWrapper::createResource($downloadResponse, $externalClient),
                ],
                ]
            );

            if ($uploadResponse->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $uploadResponse->getStatusCode());
            }
        } catch (Exception $e) {
            throw new BeeyException('Error uploading media file from URL to project ' . $projectId . ': ' . $e->getMessage());
        }
    }

    /**
     * Enqueues a project for transcription.
     *
     * @param int                 $projectId Project ID
     * @param array<string,mixed> $options   Optional transcription parameters
     * 
     * @see https://docs.beey.io/v2/transcription/01-enqueue_project.html
     * 
     * @throws BeeyException
     * @return Project Updated project
     */
    public function enqueueProject(int $projectId, array $options = []): Project
    {
        // Explicitly define default Beey values to prevent future changes on the API side
        $defaultOptions = [
            'StartTranscriptionAt' => null,
            'TranscriptionDuration' => null,
            'Lang' => 'cs-CZ',
            // Settings regarding formatting of time data and capitalization of words
            'WithPPC' => 'true',
            // Settings regarding the quality of the recording, which can help recognize more words in low-quality recordings
            'WithVAD' => 'true',
            'WithPunctuation' => 'true',
            'SaveTrsx' => 'true',
            'WithSpeakerId' => 'false',
            // Recognize speakers or not
            'WithDiarization' => 'true',
            'DiarizationProfile' => null,
            'TranscriptionProfile' => null,
            // Use replacement rules
            'WithUserLex' => 'true',
            'StartTranscriptionAtSeconds' => null,
            'TranscriptionDurationInSeconds' => null,
            'ExternalMessageStreams' => null,
            'WithSongParagraphs' => null,
        ];
        try {
            $options = array_merge($defaultOptions, $options);
            $options = array_filter($options, static fn($value) => $value !== null);

            $response = $this->beeyTranscriber->request(
                'GET', 'projects/' . $projectId . '/enqueue', [
                'query' => $options,
                ]
            );

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $response->getStatusCode());
            }

            return Project::fromApi($response->toArray());
        } catch (Exception $e) {
            throw new BeeyException('Error enqueuing project ' . $projectId . ': ' . $e->getMessage());
        }
    }

    /**
     * Fetches the media file content for a project.
     *
     * @param int $projectId Project ID
     * 
     * @throws BeeyException
     * @return string Media file content
     */
    public function getProjectMediaFile(int $projectId): string
    {
        try {
            $response = $this->beeyTranscriber->request('GET', 'projects/' . $projectId . '/files/mediafile');

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $response->getStatusCode());
            }

            return $response->getContent();
        } catch (Exception $e) {
            throw new BeeyException('Error fetching media file for project ' . $projectId . ': ' . $e->getMessage());
        }
    }

    /**
     * Fetches the TRSX content for a project.
     *
     * @param  int $projectId Project ID
     * @throws BeeyException
     * 
     * @return string TRSX content
     */
    public function getTrsx(int $projectId): string
    {
        try {
            $response = $this->beeyTranscriber->request('GET', 'projects/' . $projectId . '/files/trsx');

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $response->getStatusCode());
            }

            return $response->getContent();
        } catch (Exception $e) {
            throw new BeeyException('Error fetching TRSX for project ' . $projectId . ': ' . $e->getMessage());
        }
    }

    /**
     * Returns available formats for subtitle export.
     *
     * @throws BeeyException
     * @return array<int, array{'Id': string, 'Extension': string, 'Description': string}>
     */
    public function getSubtitleExportFormats(): array
    {
        try {
            $response = $this->beeyTranscriber->request('GET', 'projects/export/subtitles/fileformats');

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $response->getStatusCode());
            }

            $data = $response->toArray();
            return $data['Data'] ?? [];
        } catch (Exception $e) {
            throw new BeeyException('Error fetching subtitle export formats: ' . $e->getMessage());
        }
    }

    /**
     * Returns available variants for subtitle export.
     *
     * @throws BeeyException
     * @return array<int, array{'Id': string, 'Description': string}>
     */
    public function getSubtitleExportVariants(): array
    {
        try {
            $response = $this->beeyTranscriber->request('GET', 'projects/export/subtitles/variants');

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $response->getStatusCode());
            }

            $data = $response->toArray();
            return $data['Data'] ?? [];
        } catch (Exception $e) {
            throw new BeeyException('Error fetching subtitle export variants: ' . $e->getMessage());
        }
    }

    /**
     * Returns available formats for project export.
     *
     * @throws BeeyException
     * @return array<int, array{'Id': string, 'Extension': string, 'Description': string}>
     */
    public function getExportProjectFormats(): array
    {
        try {
            $response = $this->beeyTranscriber->request('GET', 'projects/export/formats');

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $response->getStatusCode());
            }

            $data = $response->toArray();
            return $data['Data'] ?? [];
        } catch (Exception $e) {
            throw new BeeyException('Error fetching project export formats: ' . $e->getMessage());
        }
    }

    /**
     * Exports a project in the selected format.
     *
     * @param int    $projectId Project ID
     * @param string $format    Id of the format you want to export. You can find options using getExportProjectFormats()
     * 
     * @see https://docs.beey.io/v2/exports/03-export_project.html
     * 
     * @throws BeeyException
     * @return string Content of the exported project in the desired format
     */
    public function exportProject(int $projectId, string $format = 'txt'): string
    {
        $defaultQueryParams = [
            'FormatId' => $format,
            'WithTimeStamps' => 'false',
            'FrontEndNormalize' => 'true',
            'IsRightToLeft' => 'false',
        ];
        
        try {
            $response = $this->beeyTranscriber->request(
                'GET', 'projects/' . $projectId . '/export', [
                'query' => $defaultQueryParams
                ]
            );

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $response->getStatusCode());
            }

            return $response->getContent();
        } catch (Exception $e) {
            throw new BeeyException('Error exporting project ' . $projectId . ': ' . $e->getMessage());
        }
    }

    /**
     * Exports subtitles for a project in the selected format.
     *
     * @param int                 $projectId    Project ID
     * @param string              $FileFormatId File format ID (e.g., 'srt')
     * @param array<string,mixed> $options      Optional switches and parameters
     * 
     * @see https://docs.beey.io/v2/exports/04-export_subtitles.html
     * 
     * @throws BeeyException
     * @return string Content of the exported file
     */
    public function exportSubtitles(
        int $projectId,
        string $FileFormatId,
        array $options = [],
    ): string {
        $defaultQueryParams = [
            'FileFormatId' => $FileFormatId,
            'VariantId' => null,
            'SubtitleLineLength' => null,
            'KeepStripped' => 'false',
            'CodePageNumber' => null,
            'DiskFormatCode' => null,
            'DisplayStandardCode' => null,
            'LanguageCode' => null,
            'UseBoxAroundText' => null,
            'ForceSingleLine' => 'false',
            'SpeakerSignPlacement' => null,
            'PauseBetweenCaptionsMs' => 80,
            'AutofillPauseBetweenCaptionsMs' => 0,
            'UseSpeakerName' => 'false',
            'Language' => null,
            'RemoveNoises' => 'true',
            'CharsPerSecond' => 16,
            'MinLineDurationMs' => 2000,
            'Ellipsis' => '…',
            'EllipsisGapDurationMs' => 300,
            'SpeakerSign' => '-',
            'FormattingMode' => null,
            'MakeAllUpperCase' => 'false',
            'KeepInnerLinesStripped' => 'false',
            'IsRightToLeft' => 'false',
            'HighlightingMode' => 'None',
            'UnhighlightedColor' => 'White',
            'UnhighlightedBackgroundColor' => 'White',
        ];

        $queryParams = array_merge($defaultQueryParams, $options);
        $queryParams = array_filter($queryParams, static fn($value) => $value !== null);

        try {
            $response = $this->beeyTranscriber->request(
                'GET', 'projects/' . $projectId . '/export/subtitles', [
                'query' => $queryParams,
                ]
            );

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $response->getStatusCode());
            }

            return $response->getContent();
        } catch (Exception $e) {
            throw new BeeyException('Error exporting subtitles for project ' . $projectId . ': ' . $e->getMessage());
        }
    }

    /**
     * Adds a tag to a project.
     *
     * @param int    $projectId   Project ID
     * @param string $tag         ag text
     * @param int    $accessToken Project access token (can be obtained from getProject())
     * 
     * @throws BeeyException
     * @return Project Updated project
     */
    public function addTag(int $projectId, string $tag, int $accessToken): Project
    {
        try {
            $response = $this->beeyTranscriber->request(
                'POST', 'projects/' . $projectId . '/tags', [
                'query' => [
                    'AccessToken' => $accessToken,
                ],
                'json' => [
                    'Tag' => $tag
                ],
                ]
            );

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $response->getStatusCode());
            }

            return Project::fromApi($response->toArray());
        } catch (Exception $e) {
            throw new BeeyException('Error adding tag to project ' . $projectId . ': ' . $e->getMessage());
        }
    }

    /**
     * Returns the list of tags for a project.
     *
     * @param int $projectId Project ID
     * 
     * @throws BeeyException
     * @return string[] List of tags
     */
    public function getTags(int $projectId): array
    {
        try {
            $response = $this->beeyTranscriber->request('GET', 'projects/' . $projectId . '/tags');

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $response->getStatusCode());
            }

            $data = $response->toArray();
            return $data['Data']['Tags'] ?? [];
        } catch (Exception $e) {
            throw new BeeyException('Error fetching tags for project ' . $projectId . ': ' . $e->getMessage());
        }
    }

    /**
     * Removes a tag from a project.
     *
     * @param int    $projectId   Project ID
     * @param string $tag         Tag text
     * @param int    $accessToken Project access token (can be obtained from getProject())
     * 
     * @throws BeeyException
     * @return Project Updated project
     */
    public function deleteTag(int $projectId, string $tag, int $accessToken): Project
    {
        try {
            $response = $this->beeyTranscriber->request(
                'DELETE', 'projects/' . $projectId . '/tags', [
                'query' => [
                    'AccessToken' => $accessToken,
                ],
                'json' => [
                    'Tag' => $tag
                ],

                ]
            );

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Returned status code ' . $response->getStatusCode());
            }

            return Project::fromApi($response->toArray());
        } catch (Exception $e) {
            throw new BeeyException('Error removing tag from project ' . $projectId . ': ' . $e->getMessage());
        }
    }

}
