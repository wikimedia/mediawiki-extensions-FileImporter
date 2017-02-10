<?php

namespace FileImporter;

// TODO implement real logic here
class ExternalMediaWikiFile implements Importable {

	public function getTitle() {
		return 'Berlin_Montage_4';
	}

	public function getImageUrl() {
		return 'https://upload.wikimedia.org/wikipedia/commons/5/52/Berlin_Montage_4.jpg';
	}

	public function getTargetUrl() {
		return 'https://en.wikipedia.org/wiki/File:Berlin_Montage_4.jpg';
	}

}
