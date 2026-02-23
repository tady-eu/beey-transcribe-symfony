## Použití

### Příklady

#### Transkripce audia (OnDemand)
```PHP
public function ControllerMethod(BeeyTranscriber $beeyTranscriber){
	// Nový projekt
	$project = $beeyTranscriber->addProject('Nějaký název');
	// získáme ID projektu
	$id = $project->getId();

	// Nahrajeme audio k transkripci
	$beeyTranscriber->uploadMediaFile($id, '/cesta_k_audiu/test-short.mp3');

	// Spuštění samotné transkripce
	$beeyTranscriber->enqueueProject($id);

	// Získání stavu transkripce
	$state = $beeyTranscriber->getProject($id)->getProcessingState();

	// Nutné vyčkat na dokončení transkripce

	// Stažení srt titulků (získání dostupných formátů pomocí getSubtitleExportFormats())
	$subtitles = $beeyTranscriber->exportSubtitles($id, 'srt');

	// Stažení trsx souboru
	$trsxContent = $beeyTranscriber->getTrsx($id);
}
```

## Instalace
Dopnit proměnné do .env.local souboru
```env
BEEY_TRANSCRIBER_URI="http://FILL_ME/XAPI/v2/"
BEEY_TRANSCRIBER_KEY="FILL_ME"
```
Nastavení http clienta v Symfony (`framework.yaml`)
```yaml
http_client:
	scoped_clients:
		beeyTranscriber:
			base_uri: "%env(BEEY_TRANSCRIBER_URI)%"
			timeout: 60
			headers:
				"authorization": "%env(BEEY_TRANSCRIBER_KEY)%"
```