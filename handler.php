<?php

error_reporting(0);

// $filename: Path to debian package of which the control information is to be retrieved
function GetPkgInfo($filename)
{
	// Open file
	if (($ar = fopen($filename, "rb")) === false)
		return false;
	$ar_stat = fstat($ar);

	// Check for the magic ASCII string
	if (($magic = fread($ar, 8)) === false)
	{
		fclose($ar);
		return false;
	}
	$magic = unpack("a8", $magic);
	if (strcmp("!<arch>\n", $magic[1]))
	{
		fclose($ar);
		return false;
	}

	// Start reading file headers
	// Ar-File headers are 60 bytes in size
	while (($file_header_string = fread($ar, 60)) !== false)
	{
		$file_header = unpack("A16name/A12timestamp/A6owner/A6group/A8mode/A10size/C2magic", $file_header_string);
		// Check for the file magic number
		if ($file_header['magic1'] != 0x60 || $file_header['magic2'] != 0x0A)
		// Assume the file is damaged
		{
			fclose($ar);
			return false;
		}
		// Check if the file name is control.tar or control.tar.gz
		// control.tar.xz is not supported by this implementation
		if (!strcmp($file_header['name'], "control.tar") || !strcmp($file_header['name'], "control.tar.gz"))
		{
			// Found the control.tar/control.tar.gz file
			// Read its contents

			$control_size = intval($file_header['size']);
			if (($pos = ftell($ar)) === false || $pos + $control_size >= $ar_stat['size'])
			{
				fclose($ar);
				return false;
			}
			$control_string = fread($ar, $control_size);
			fclose($ar);
			if ($control_string === false)
				return false;

			if (!strcasecmp($file_header['name'], "control.tar.gz"))
				// control.tar.gz needs to be decoded
				if (($control_string = gzdecode($control_string)) === false)
					return false;

			// Extract file "./control" from tape archive
			while (strlen($control_string) >= 512)
			{
				// Read header
				$tar_header = unpack("A100name/A8mode/A8owner/A8group/A12size/A12timestamp/A8checksum/A1type/A355padding", $control_string);
				// Check checksum
				$control_string = substr_replace($control_string, "\x20\x20\x20\x20\x20\x20\x20\x20", 148, 8);
				$calculated_sum = 0;
				for ($i = 0; $i < 512; $i++)
				{
					$calculated_sum += ord($control_string[$i]);
				}
				if (intval($tar_header['checksum'], 8) != $calculated_sum)
					// Assume the file is damaged
					return false;
				// Check if file is a normal file
				if ($tar_header['type'] == 0x00 || intval($tar_header['type']) == 0)
				{
					// Check if filename equals "./control"
					if (!strcmp($tar_header['name'], "./control"))
					{
						// Read the control file
						$size = intval($tar_header['size'], 8);
						$reqfl = 512 + $size;
						if ($reqfl >= strlen($control_string))
							return false;
						$control_string = substr($control_string, 512, $size);
						$control_fields = explode("\n", $control_string);
						$control = array();
						foreach ($control_fields as $field) {
							if (!strlen($field))
								continue;
							$tmp = explode(":", $field, 2);
							$control[$tmp[0]] = trim($tmp[1]);
						}
						// Check if basic package information (id and version) is available
						if (!isset($control['Package']) || !isset($control['Version']))
							return false;
						// Add size of debian package
						$control['Size'] = $ar_stat['size'];
						$control['MD5sum'] = md5_file($filename);
						if ($control['MD5sum'] === false)
							return false;
						$control["Filename"] = $filename;
						return $control;
					}
				}

				// Skip until next file
				$bytes = 512 + intval($tar_header['size'], 8);
				$padding = $bytes % 512;
				$padding = !$padding ? $padding : 512 - $padding;
				$bytes += $padding;

				if ($bytes >= strlen($control_string))
					return false;
				$control_string = substr($control_string, $bytes);
			}
		}

		// Seek to the next file header
		$file_size = intval($file_header['size']);
		if ($file_size & 0x1)
			$file_size++;
		if (($pos = ftell($ar)) === false || $pos + $file_size >= $ar_stat['size'])
			return false;
		fseek($ar, $file_size, SEEK_CUR);
	}
	fclose($ar);
	return false;
}


function CompareVersionNumber($ver1, $ver2)
{
	for ($i = 0, $vl1 = strlen($ver1), $vl2 = strlen($ver2); $i < $vl1 && $i < $vl2; $i++)
	{
		$val1 = ord($ver1[$i]);
		$val2 = ord($ver2[$i]);
		if ($ver1[$i] == '~')
			$val1 = 0;
		if ($ver2[$i] == '~')
			$val2 = 0;
		if (!ctype_alnum($ver1[$i]))
			$val1 += 0xFF;
		if (!ctype_alnum($ver2[$i]))
			$val2 += 0xFF;
		if ($val1 > $val2)
			return 1;
		else if ($val1 < $val2)
			return -1;
	}
	return 0;
}

// $ver1: First version number
// $ver2: Second version number
// returns: -1 if $ver1 < $ver2
//			0 if $ver1 == $ver2
//			1 if $ver1 > $ver2
function CompareVersions($ver1, $ver2)
{
	// Prepare associative arrays for version numbers
	$version1 = array("epoch" => 0, "debian_revision" => "", "upstream_version" => $ver1);
	$version2 = array("epoch" => 0, "debian_revision" => "", "upstream_version" => $ver2);

	// split version into epoch, upstream_version and debian_revision

	// Retrieve epoch
	$epoch = explode(":", $ver1, 2);
	if (count($epoch) == 2)
	{
		$version1['epoch'] = intval($epoch[0]);
		$version1['upstream_version'] = $epoch[1];
	}
	$epoch = explode(":", $ver2, 2);
	if (count($epoch) == 2)
	{
		$version2['epoch'] = intval($epoch[0]);
		$version2['upstream_version'] = $epoch[1];
	}

	// Compare epoch
	if ($version1['epoch'] > $version2['epoch'])
		return 1;
	else if ($version1['epoch'] < $version2['epoch'])
		return -1;

	// Retrieve debian_revision
	$version1['debian_revision'] = strrchr($ver1, "-");
	if ($version1['debian_revision'] === false)
		$version1['debian_revision'] = "";
	$version2['debian_revision'] = strrchr($ver2, "-");
	if ($version2['debian_revision'] === false)
		$version2['debian_revision'] = "";

	// Retrieve upstream version
	$rev_pos = strrpos($version1['upstream_version'], "-");
	if ($rev_pos !== false)
		$version1['upstream_version'] = substr($version1['upstream_version'], 0, $rev_pos);
	$rev_pos = strrpos($version2['upstream_version'], "-");
	if ($rev_pos !== false)
		$version2['upstream_version'] = substr($version2['upstream_version'], 0, $rev_pos);

	// Compare upstream version
	$cmpres = CompareVersionNumber($version1['upstream_version'], $version2['upstream_version']);
	if ($cmpres)
		return $cmpres;

	// Compare debian revision
	$cmpres = CompareVersionNumber($version1['debian_revision'], $version2['debian_revision']);
	return $cmpres;
}

// $dir: Directory to search for debian packages
function GeneratePackages($dir)
{
	if (($debs = glob($dir . "/*.deb")) === false)
		return false;
	$packages = array();
	foreach ($debs as $deb) {
		if (($control = GetPkgInfo($deb)) === false)
			continue;

		if (!isset($packages[$control['Package']]))
			$packages[$control['Package']] = $control;
		else
			// Compare version numbers
			if (CompareVersions($control['Version'], $packages[$control['Package']]['Version']) > 0)
				$packages[$control['Package']] = $control;
	}

	$packages_string = "";
	foreach ($packages as $info)
	{
		foreach ($info as $fieldname => $value)
			$packages_string .= $fieldname . ": " . $value . "\n";
	
		$packages_string .= "\n";
	}
	return $packages_string;
}

// Generate new Packages.bz2 if Packages.bz2 does not exist or if the time of the last modification was
// before the time of the last modification of the debs-Directory
$size = 0;
if (!($exists = file_exists("Packages.bz2")) || ($size = filesize("Packages.bz2")) === false || ($pbz2mtime = filemtime("Packages.bz2")) === false || ($debsmtime = filemtime("debs/.")) === false || $pbz2mtime < $debsmtime)
{
	// Return the old or a fake Packages.bz2
	ob_end_clean();
	header("Connection: close");
	ignore_user_abort(1);
	ob_start();
	header("Content-Type: application/x-bzip2");
	header("Content-Disposition: filename=\"Packages.bz2\"");
	if ($exists && $size !== false) {
		readfile("Packages.bz2");
		header("Content-Length: " . $size);
	} else {
		$fakepkg = bzcompress("");
		echo $fakepkg;
		header("Content-Length: " . strlen($fakepkg));
	}
	ob_end_flush();
	flush();
	session_write_close();

	// Generate new Packages.bz2

	// Exit if the Packages.bz2 file is locked by another instance of this script
	if (file_exists("Packages.lock"))
		exit();
	// Create lock
	file_put_contents("Packages.lock", "");
	// Retrieve Packages string for "debs"-Directory
	$packages = GeneratePackages("debs");
	if ($packages !== false)
		file_put_contents("Packages.bz2", bzcompress($packages));

	// Remove lock
	unlink("Packages.lock");
	exit();
}
// Return the current Packages.bz2
else
{
	header("Content-Type: application/x-bzip2");
	header("Content-Disposition: filename=\"Packages.bz2\"");
	header("Content-Length: " . $size);
	readfile("Packages.bz2");	
}