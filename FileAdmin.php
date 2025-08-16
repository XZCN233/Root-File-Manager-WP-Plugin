<?php

$PASSWORD = '$2y$10$RQSgmHK06ENy/OFMPfKHnO3urzF.6mYHJujTjvlSDEN18Sk4dUfEa';

error_reporting(0);
$VERSION = "8.25";
$HOST = "https://fa.nlr.simsoft.top";
$LOGIN = false;

$verifyString = md5(date("Ymd") .  $_SERVER['SERVER_SOFTWARE'] . php_uname() . phpversion());
if (password_verify($PASSWORD . $verifyString, $_COOKIE["FileAdmin_Token"])) $LOGIN = true;
switch($_GET["run"]){

// 处理后端请求 =================================================================
case "backend":
	
	// 解码参数
	function getParam($paramName) {
		if($_POST["data"]) {
			$base64Data = rawurldecode($_POST["data"]);
			$jsonData = base64_decode($base64Data);
			$decodedData = json_decode($jsonData, true);
			return $decodedData[$paramName];
		}
		return null;
	}
	// 返回数据
	function returnData($data){
		die(json_encode($data));
	}
	// 递归删除
	function deleteFile($path) {
		$successCount = 0;
		$totalCount = 0;
		if (is_file($path)) {
			unlink($path);
			if(!file_exists($path)) $successCount++;
			$totalCount++;
		} elseif (is_dir($path)) {
			$files = array_diff(scandir($path), [".", ".."]);
			foreach ($files as $file) {
				$result = deleteFile($path . "/" . $file);
				$successCount += $result["successCount"];
				$totalCount += $result["totalCount"];
			}
			rmdir($path);
		}
		return ["successCount" => $successCount, "totalCount" => $totalCount];
	}
	// 保存文件
	function saveFile($path, $content) {
		file_put_contents($path, $content, LOCK_EX);
		if (file_get_contents($path) == $content) return true;
	}
	// 判断二进制文件
	function isBinary($file) {
		$contents = file_get_contents($file, false, null, 0, 100);
		return strpos($contents, "\x00");
	}
	// 复制文件
	function copyFile($original, $destination) {
		if (is_file($original)) return copy($original, $destination);		
		if (!is_dir($destination)) mkdir($destination, 0777, true);
		$files = scandir($original);
		foreach ($files as $file) {
			if (strpos($original . "/" . $file ,$destination) !== false) return false;
			if ($file != "." && $file != "..") {
				if (!copyFile($original . "/" . $file, $destination . "/" .$file)) return false;
			}
		}
		return true;
	}
	// 打包文件
	function zipFile($dir, $paths, $name) {
		$zip = new ZipArchive();
		if (!$zip->open($name, ZipArchive::CREATE | ZipArchive::OVERWRITE)) return false;
		foreach ($paths as $path) {
			$fullPath = $dir . '/' . $path;
			if (is_dir($fullPath)) addDirToZip($fullPath, $zip, $dir);
			else addFileToZip($fullPath, $zip, $dir);
		}
		$zip->close();
		return true;
	}
	function addFileToZip($fullPath, $zip, $baseDir) {
		if ($fullPath[0] == '.') $relativePath = ltrim($fullPath, '.');
		else $relativePath = $fullPath;
		if (is_dir("./.FileAdmin/file-modify" . $relativePath)) {
			$faRelativePath = "./.FileAdmin/file-modify" . $relativePath . "/" . scandir("./.FileAdmin/file-modify" . $relativePath)[2];
			$zip->addFile($faRelativePath, substr($fullPath, strlen($baseDir) + 1));
		} else {
			$zip->addFile($fullPath, substr($fullPath, strlen($baseDir) + 1));
		}
	}
	function addDirToZip($dir, $zip, $baseDir) {
		$handle = opendir($dir);
		while (($file = readdir($handle)) !== false) {
			if ($file == '.' || $file == '..') continue;
			$fullPath = $dir . '/' . $file;
			if (is_dir($fullPath)) addDirToZip($fullPath, $zip, $baseDir);
			else addFileToZip($fullPath, $zip, $baseDir);
		}
		closedir($handle);
	}
	// 解压文件
	function unzipFile($name, $destination, $password) {
		if (!is_dir($destination)) mkdir($destination, 0777, true);
		$zip = new ZipArchive;
		if ($zip->open($name)) {
			$zip->setPassword($password);
			$result = $zip->extractTo($destination);
			$zip->close();
			return $result?200:1001;
		} else {
			return 1002;
		}
	}
	// 解压文件
	function calcSize($path) {
		if (is_file($path)) return filesize($path);
		if (is_dir($path)) {
			$size = 0;
			$dirHandle = opendir($path);
			while (($file = readdir($dirHandle)) !== false) {
				if ($file != "." && $file != "..") {
					$childPath = $path . "/" . $file;
					$size += calcSize($childPath);
				}
			}
			closedir($dirHandle);
			return $size;
		}
	}
	// 搜索文件
	function searchFiles($pathOriginal, $searchValue, $searchContent, $includeSubDir, $caseSensitive, $fileExt) {
		$path = "." . $pathOriginal;
		$results = [];
		if (is_dir($path)) {
			$files = scandir($path);
			foreach ($files as $file) {
				if ($file != '.' && $file != '..') {
					$filePath = $path . '/' . $file;
					if (is_file($filePath)) {
						if ($fileExt[0] && !in_array(strtolower(end(explode(".", $filePath))), $fileExt)) continue;
						if ($pathOriginal . "/" . $file == "/" . end(explode("/", $_SERVER["SCRIPT_NAME"]))) continue;
						$fileName = $file;
						if (!$caseSensitive) {
							$fileName = strtolower($fileName);
							$searchValue = strtolower($searchValue);
						}
						if ($searchContent) {
							$originalContent = file_get_contents($filePath);
							if (isBinary($filePath)) continue;
							$content = $originalContent;
							if (!$caseSensitive) {
								$content = strtolower($originalContent);
								$searchValue = strtolower($searchValue);
							}
							$searchResults = [];
							$originalContentExplode = explode("\n", $originalContent);
							foreach (explode("\n", $content) as $lineNumber => $lineContent) {
								if (strpos($lineContent, $searchValue) !== false) $searchResults[] = [$lineNumber + 1, $originalContentExplode[$lineNumber]];
							}
							if (!empty($searchResults)) {
								$resultItem = [ "name" => $pathOriginal . "/" . $file, "search" => $searchResults ];
								$results[] = $resultItem;
							}
						} else {
							if (strpos($fileName, $searchValue) !== false) {
								$resultItem = [ "name" => $pathOriginal . "/" . $file ];
								$results[] = $resultItem;
							}
						}
					} elseif ($includeSubDir && is_dir($filePath)) {
						if ($pathOriginal . "/" . $file == "/.FileAdmin") continue;
						$subResults = searchFiles($pathOriginal . "/" . $file, $searchValue, $searchContent, $includeSubDir, $caseSensitive, $fileExt);
						$results = array_merge($results, $subResults);
					}
				}
			}
		}
		return $results;
	}
	// Base32编解码
	function base32Encode($data) {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$out = '';
		$v = 0;
		$vbits = 0;
		for ($i = 0, $j = strlen($data); $i < $j; $i++) {
			$v <<= 8;
			$v += ord($data[$i]);
			$vbits += 8;
			while ($vbits >= 5) { $vbits -= 5; $out .= $alphabet[$v >> $vbits]; $v &= ((1 << $vbits) - 1); }
		}
		if ($vbits > 0) { $v <<= (5 - $vbits); $out .= $alphabet[$v]; }
		return $out;
	}
	function base32Decode($data) {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$out = '';
		$bits = 0;
		$value = 0;
		for ($i = 0; $i < strlen($data); $i++) {
			$index = strpos($alphabet, $data[$i]);
			$value = ($value << 5) | $index;
			$bits += 5;
			if ($bits >= 8) { $bits -= 8; $out .= chr(($value & (0xff << $bits)) >> $bits); }
		}
		return $out;
	}
	// 验证TOTP密钥
	function verifyTotp($content) {
		$timestamp = floor(time() / 30);
		$binaryKey = base32Decode(scandir("./.FileAdmin/totp-data")[2]);
		$timestamp = pack('N*', 0) . pack('N*', $timestamp);
		$hash = hash_hmac('sha1', $timestamp, $binaryKey, true);
		$offset = ord(substr($hash, -1)) & 0x0F;
		$otpToCheck = (
			((ord($hash[$offset + 0]) & 0x7F) << 24) |
			((ord($hash[$offset + 1]) & 0xFF) << 16) |
			((ord($hash[$offset + 2]) & 0xFF) << 8) |
			(ord($hash[$offset + 3]) & 0xFF)
		) % pow(10, 6);
		$otpToCheck = str_pad($otpToCheck, 6, '0', STR_PAD_LEFT);
		if ($otpToCheck == $content) return true;
	}

	// 处理请求
	header("content-type: text/json");
	switch(getParam("action")){
		// 获取版本
		case "version":
			returnData([ "version" => $VERSION , "isLogin" => $LOGIN , "time" => time() , "faScript" => end(explode("/", $_SERVER["SCRIPT_NAME"])) ]);
		break;
		// 用户登录 —— 成功码 200 / 密码错 1001 / 已拉黑 1002 / 需要TOTP 1003 / 错误TOTP 1004
		case "login":
			// 检查失败记录
			$fileName = "./.FileAdmin/failed-login/" . md5($_SERVER["REMOTE_ADDR"]);
			if (!file_exists($fileName)) $allowLogin = true;
			else if (file_get_contents($fileName) < 5) $allowLogin = true;
			if (!$allowLogin) returnData([ "code" => 1002 ]);
			// 检查TOTP配置
			if (file_get_contents("./.FileAdmin/totp-data/" . scandir("./.FileAdmin/totp-data")[2]) == 1) {
				if (!getParam("totp")) returnData([ "code" => 1003 ]);
				if (!verifyTotp(getParam("totp"))) returnData([ "code" => 1004 ]);
			}
			// 开始判断登录
			if (password_verify(getParam("password"), $PASSWORD)){
				setcookie("FileAdmin_Token", password_hash($PASSWORD . $verifyString ,PASSWORD_DEFAULT));
				if (file_exists($fileName)) unlink($fileName);
				returnData([ "code" => 200 ]);
			}
			// 记录失败登录
			if (!is_dir("./.FileAdmin/failed-login")) mkdir("./.FileAdmin/failed-login", 0777, true);
			if (!file_exists($fileName)) file_put_contents($fileName, "1");
			else file_put_contents($fileName, file_get_contents($fileName) + 1);
			returnData([ "code" => 1001 ]);
		break;
		// 用户注销
		case "logout":
			setcookie("FileAdmin_Token", "<revoked>");
			returnData([]);
		break;
		// 修改密码 —— 成功码 200 / 未登录 1000 / 无权限 1001
		case "changePassword":
			if(!$LOGIN) returnData([ "code" => 1000 ]);
			$newPassword = password_hash(getParam("password") ,PASSWORD_DEFAULT);
			$faScript = end(explode("/", $_SERVER["SCRIPT_NAME"]));
			$replaceContent = str_replace($PASSWORD, $newPassword, file_get_contents($faScript));
			if (saveFile($faScript, $replaceContent)) {
				setcookie("FileAdmin_Token", password_hash($newPassword . date("Ymd"), PASSWORD_DEFAULT));
				returnData([ "code" => 200 ]);
			}
			returnData([ "code" => 1001 ]);
		break;
		// 更新版本 —— 成功码 200 / 未登录 1000 / 下载失败 1001 / 无权限 1002
		case "getLatestVersion":
			if(!$LOGIN) returnData([ "code" => 1000 ]);
			$updateContent = file_get_contents($HOST . "/backend/downupdate");
			if (!$updateContent) returnData([ "code" => 1001 ]);
			$faScript = end(explode("/", $_SERVER["SCRIPT_NAME"]));
			$defaultPassword = "<FileAdmin Update Default Password>";
			$pos = strpos($updateContent, $defaultPassword);
			$replaceContent = substr($updateContent, 0, $pos);
			$replaceContent .= $PASSWORD;
			$replaceContent .= substr($updateContent, $pos + strlen($defaultPassword));
			if (saveFile($faScript, $replaceContent)) returnData([ "code" => 200 ]);
			returnData([ "code" => 1002 ]);
		break;
		// PHP信息 —— 成功码 200 / 未登录 1000
		case "phpinfo":
			if(!$LOGIN) returnData([ "code" => 1000 ]);
			ob_start();
			phpinfo();
			$info = ob_get_contents();
			ob_end_clean();
			returnData([ "code" => 200 , "info" => $info ]);
		// 读取目录 —— 成功码 200 / 未登录 1000 / 目录不存在 1001
		case "dir":
			if(!$LOGIN) returnData([ "code" => 1000 ]);
			$realDir = "." . getParam("dir");
			if(is_dir($realDir) && !in_array(".", explode("/", getParam("dir"))) && !in_array("..", explode("/", getParam("dir")))) {
				$filesArray = [];
				$scanResult = scandir($realDir);
				foreach ($scanResult as $name) {
					if ($name == "." || $name == "..") continue;
					$filesArray []= [ "name" => getParam("dir") . "/" . $name, "isDir" => is_dir("." . getParam("dir") . "/" . $name) , "size" => filesize("." . getParam("dir") . "/" . $name) ];
				}
				returnData([ "code" => 200, "files" => $filesArray]);
			}
			else returnData([ "code" => 1001 ]);
		break;
		// 获取文件内容 —— 成功码 200 / 未登录 1000 / 文件不存在 1001 / 文件过大 1002 / 无法读取 1003
		case "file":
			if (!$LOGIN) returnData([ "code" => 1000 ]);
			$realDir = "." . getParam("file");
			if (is_dir("./.FileAdmin/file-modify" . getParam("file"))) $realDir = "./.FileAdmin/file-modify" . getParam("file") . "/" . scandir("./.FileAdmin/file-modify" . getParam("file"))[2];
			if (!file_exists($realDir)) returnData([ "code" => 1001 ]);
			if (filesize($realDir) > 4*1024*1024) returnData([ "code" => 1002 ]);
			$content = file_get_contents($realDir);
			if($content !== false) returnData([ "code" => 200, "content" => $content]);
			returnData([ "code" => 1003 ]);
		break;
		// 文件分片上传 —— 成功码 200 / 未登录 1000 / 已存在 1001
		case "upload":
			if(!$LOGIN) returnData([ "code" => 1000 ]);
			$targetDir = "." . getParam("dir");
			$chunkDir = "./.FileAdmin/upload-chunks" . getParam("dir");
			if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
			if (!file_exists($chunkDir)) mkdir($chunkDir, 0777, true);
			$fileName = getParam("name");
			if (file_exists($targetDir . "/" . $fileName)) returnData([ "code" => 1001 ]);
			$chunkName = $fileName . ".FileAdminPart" . getParam("currentChunk");
			move_uploaded_file($_FILES["file"]["tmp_name"], $chunkDir . "/" . $chunkName);
			if (file_exists($chunkDir . "/" . $chunkName)) $uploadSuccess = true;
			if (getParam("currentChunk") == getParam("totalChunks") - 1) {
				$fileHandle = fopen($targetDir . "/" . $fileName, "ab");
				for ($i = 0; $i < getParam("totalChunks"); $i++) {
					$chunkName = $fileName . ".FileAdminPart" . $i;
					$chunkHandle = fopen($chunkDir . "/" . $chunkName, "rb");
					fwrite($fileHandle, fread($chunkHandle, filesize($chunkDir . "/" . $chunkName)));
					fclose($chunkHandle);
					unlink($chunkDir . "/" . $chunkName);
				}
				fclose($fileHandle);
				deleteFile($chunkDir);
				if(file_exists($targetDir . "/" . $fileName)) $fileSuccess = true;
			}
			returnData([ "code" => 200, "uploadSuccess" => $uploadSuccess, "fileSuccess" => $fileSuccess ]);
		break;
		// 文本文件保存 —— 成功码 200 / 未登录 1000 / 文件不存在 1001 / 权限错误等 1002
		case "save":
			if(!$LOGIN) returnData([ "code" => 1000 ]);
			$fileRealPath = realpath("." . getParam("file"));
			if(!is_file($fileRealPath)) returnData([ "code" => 1001 ]);
			deleteFile("./.FileAdmin/file-modify" . getParam("file"));
			if (!getParam("modified")) {
				$saveResult = saveFile($fileRealPath, getParam("content"));
			} else {
				mkdir("./.FileAdmin/file-modify" . getParam("file"), 0777, true);
				$saveResult = saveFile($fileRealPath, getParam("modified")) && saveFile("./.FileAdmin/file-modify" . getParam("file") . "/" . md5(getParam("content")), getParam("content"));
			}
			clearstatcache();
			if($saveResult) returnData([ "code" => 200, "size" => filesize($fileRealPath) ]);
			else returnData([ "code" => 1002 ]);
		break;
		// 新建文件 —— 成功码 200 / 未登录 1000 / 文件已存在 1001 / 权限错误等 1002
		case "new":
			if(!$LOGIN) returnData([ "code" => 1000 ]);
			$fileRealPath = "." . getParam("file");
			if(file_exists($fileRealPath)) returnData([ "code" => 1001 ]);
			switch(getParam("type")) {
				case "file":
					if(!file_exists(dirname($fileRealPath))) mkdir(dirname($fileRealPath), 0777, true);
					file_put_contents($fileRealPath, "");
				break;
				case "folder":
					mkdir($fileRealPath, 0777, true);
				break;
			}
			if(file_exists($fileRealPath)) returnData([ "code" => 200 ]);
			returnData([ "code" => 1002 ]);
		break;
		// 删除文件 —— 成功码 200 / 未登录 1000 / 有删除失败 1001
		case "del":
			if(!$LOGIN) returnData([ "code" => 1000 ]);
			$totalCount = 0;
			$successCount = 0;
			foreach (getParam("files") as $file) {
				$data = deleteFile("." . $file);
				deleteFile("./.FileAdmin/file-modify" . $file);
				$totalCount += $data["totalCount"];
				$successCount += $data["successCount"];
			}
			if($totalCount == $successCount) returnData([ "code" => 200, "success" => $successCount]);
			returnData([ "code" => 1001, "success" => $successCount, "total" => $totalCount]);
		break;
		// 重命名 —— 成功码 200 / 未登录 1000 / 文件不存在 1001 / 特殊字符 1002 / 文件已存在 1003 / 改名失败 1004
		case "rename":
			if (!$LOGIN) returnData([ "code" => 1000 ]);
			if (!is_dir("." . getParam("dir")) || !file_exists("." . getParam("dir") . "/" . getParam("original"))) returnData([ "code" => 1001 ]);
			if (strstr(getParam("new"), "/")) returnData([ "code" => 1002 ]);
			if (file_exists("." . getParam("dir") . "/" . getParam("new"))) returnData([ "code" => 1003 ]);
			$result = rename("." . getParam("dir") . "/" . getParam("original"), "." . getParam("dir") . "/" . getParam("new"));
			if (file_exists("./.FileAdmin/file-modify" . getParam("dir") . "/" . getParam("original"))) {
				mkdir( "./.FileAdmin/file-modify" . getParam("dir") . "/" . getParam("new"), 0777, true);
				rename("./.FileAdmin/file-modify" . getParam("dir") . "/" . getParam("original"), "./.FileAdmin/file-modify" . getParam("dir") . "/" . getParam("new"));
			}
			if ($result) returnData([ "code" => 200 ]);
			returnData([ "code" => 1004 ]);
		break;
		// 复制文件 —— 成功码 200 / 未登录 1000 / 文件不存在 1001 / 文件已存在 1002 / 复制失败 1003
		case "copy":
			if (!$LOGIN) returnData([ "code" => 1000 ]);
			if (!is_dir("." . getParam("destination"))) mkdir("." . getParam("destination"), 0777, true);
			foreach (getParam("files") as $file) {
				if (!file_exists("." . getParam("original") . "/" . $file)) returnData([ "code" => 1001 ]);
				if (file_exists("." . getParam("destination") . "/" . $file)) returnData([ "code" => 1002 ]);
			};
			$fileFailed = false;
			foreach (getParam("files") as $file) {
				if (!copyFile("." . getParam("original") . "/" . $file, "." . getParam("destination") . "/" . $file)) $fileFailed = true;
				if(file_exists("./.FileAdmin/file-modify" . getParam("original") . "/" . $file)) copyFile("./.FileAdmin/file-modify" . getParam("original") . "/" . $file, "./.FileAdmin/file-modify" . getParam("destination") . "/" . $file);
			};
			if($fileFailed) returnData([ "code" => 1003 ]);
			returnData([ "code" => 200 ]);
		break;
		// 剪切文件 —— 成功码 200 / 未登录 1000 / 文件不存在 1001 / 文件已存在 1002 / 复制失败 1003
		case "move":
			if (!$LOGIN) returnData([ "code" => 1000 ]);
			if (!is_dir("." . getParam("destination"))) mkdir("." . getParam("destination"), 0777, true);
			foreach (getParam("files") as $file) {
				if (!file_exists("." . getParam("original") . "/" . $file)) returnData([ "code" => 1001 ]);
				if (file_exists("." . getParam("destination") . "/" . $file)) returnData([ "code" => 1002 ]);
			};
			$fileFailed = false;
			foreach (getParam("files") as $file) {
				if (!rename("." . getParam("original") . "/" . $file, "." . getParam("destination") . "/" . $file)) $fileFailed = true;
				if (file_exists("./.FileAdmin/file-modify" . getParam("original") . "/" . $file)) {
					mkdir("./.FileAdmin/file-modify" . getParam("destination") . "/" . $file, 0777, true);
					rename("./.FileAdmin/file-modify" . getParam("original") . "/" . $file, "./.FileAdmin/file-modify" . getParam("destination") . "/" . $file);
				}
			};
			if($fileFailed) returnData([ "code" => 1003 ]);
			returnData([ "code" => 200 ]);
		break;
		// 压缩文件 —— 成功码 200 / 未登录 1000 / 文件已存在 1001 / 打包失败 1002
		case "zip":
			if (!$LOGIN) returnData([ "code" => 1000 ]);
			if (file_exists("." . getParam("name"))) returnData(["code" => 1001]);
			if (!zipFile("." . getParam("dir"), getParam("files"), "." . getParam("name"))) returnData(["code" => 1002]);
			returnData(["code" => 200]);
		break;
		// 解压文件 —— 成功码 200 / 未登录 1000 / 密码错误 1001 / 解压失败 1002
		case "unzip":
			if (!$LOGIN) returnData([ "code" => 1000 ]);
			returnData(["code" => unzipFile("." . getParam("file"), "." . getParam("destination"), getParam("password")) ]);
		break;
		// 备份文件 —— 成功码 200 / 未登录 1000 / 已被删除 1001 / 创建失败 1002
		case "createBackup":
			if (!$LOGIN) returnData([ "code" => 1000 ]);
			if (!file_exists("." . getParam("file"))) returnData([ "code" => 1001 ]);
			$result = saveFile("." . getParam("file") . "." . time() . ".bak", file_get_contents("." . getParam("file")));
			if ($result) returnData([ "code" => 200 ]);
			returnData([ "code" => 1002 ]);
		break;
		// 远程下载 —— 成功码 200 / 未登录 1000 / 下载失败 1001
		case "remoteDownload":
			if (!$LOGIN) returnData([ "code" => 1000 ]);
			$fileRealPath = "." . getParam("name");
			if (!file_exists(dirname($fileRealPath))) mkdir(dirname($fileRealPath), 0777, true);
			$result = file_get_contents(getParam("url"));
			if (!$result) returnData([ "code" => 1001 ]);
			if (!saveFile($fileRealPath, $result)) returnData([ "code" => 1001 ]);
			returnData([ "code" => 200 ]);
		break;
		// 文件属性 —— 成功码 200 / 未登录 1000 / 已被删除 1001 / 权限问题 1002
		case "props":
			if (!$LOGIN) returnData([ "code" => 1000 ]);
			$fileRealPath = "." . getParam("file");
			if (!file_exists($fileRealPath)) returnData([ "code" => 1001 ]);
			if (file_get_contents($fileRealPath) === false) returnData([ "code" => 1002 ]);
			returnData([
				"code" => 200,
				"props" => [
					"name" => end(explode("/", $fileRealPath)),
					"realpath" => realpath($fileRealPath),
					"dir" => is_dir($fileRealPath),
					"format" => end(explode(".", end(explode("/", $fileRealPath)))),
					"size" => calcSize($fileRealPath),
					"mtime" => filemtime($fileRealPath),
					"atime" => fileatime($fileRealPath),
					"owner" => function_exists("posix_getpwuid") ? posix_getpwuid(fileowner($fileRealPath))["name"] : "Windows 下无法获取",
					"perm" => substr(sprintf('%o', fileperms($fileRealPath)), -4),
				]
			]);
		break;
		// 目录分析 —— 成功码 200 / 未登录 1000 / 无此目录 1001 / 空目录 1002
		case "analyse":
			if (!$LOGIN) returnData([ "code" => 1000 ]);
			$fileRealPath = "." . getParam("dir");
			if (!is_dir($fileRealPath)) returnData([ "code" => 1001 ]);
			if (count(scandir($fileRealPath)) < 3) returnData([ "code" => 1002 ]);
			$fileSizeTotal = 0;
			$fileDetail = [];
			foreach (scandir($fileRealPath) as $file) {
				if ($file == "." || $file =="..") continue;
				$currentFile = $fileRealPath . "/" . $file;
				$currentFileSize = calcSize($currentFile);
				$fileSizeTotal += $currentFileSize;
				$fileDetail[] = [
					"name" => $file,
					"fullName" => getParam("dir") . "/" . $file,
					"isDir" => is_dir($currentFile),
					"size" => $currentFileSize,
				];
			}
			returnData(["code" => 200, "data" => [
				"diskTotal" => disk_total_space("/"),
				"diskFree" => disk_free_space("/"),
				"dirSize" => $fileSizeTotal,
				"detail" => $fileDetail,
			]]);
		break;
		// 文件搜索 —— 成功码 200 / 未登录 1000 / 无此目录 1001 / 无结果 1002 / 存在替换失败 1003
		case "search":
			if (!$LOGIN) returnData([ "code" => 1000 ]);
			if (!is_dir("." . getParam("dir"))) returnData(["code" => 1001]);
			$result = searchFiles(getParam("dir"), getParam("search"), getParam("mode")=="searchName"?false:true, getParam("subDir")=="on"?true:false, getParam("caseSensitive")=="on"?true:false, explode(" ", trim(getParam("ext"))));
			if (empty($result)) returnData(["code" => 1002]);
			if (getParam("mode") != "replaceContent") returnData(["code" => 200, "results" => $result]);
			$successCount = 0;
			$totalCount = count($result);
			foreach ($result as $resultData) {
				$replaceFile = "." . $resultData["name"];
				$replaceFileData = file_get_contents($replaceFile);
				if (!$replaceFileData) continue;
				if (getParam("caseSensitive") == "on") $replacedFileData = str_replace(getParam("search"), getParam("replace"), $replaceFileData);
				else $replacedFileData = str_ireplace(getParam("search"), getParam("replace"), $replaceFileData);
				if (saveFile($replaceFile, $replacedFileData)) $successCount++;
			}
			if ($successCount == $totalCount) returnData(["code" => 200, "total" => $totalCount]);
			returnData(["code" => 1003, "total" => $totalCount, "success" => $successCount]);
		break;
		// 查询TOTP —— 成功码 200 / 未登录 1000
		case "totpStatus":
			if (!$LOGIN) returnData([ "code" => 1000 ]);
			$enabledStatus = file_get_contents("./.FileAdmin/totp-data/" . scandir("./.FileAdmin/totp-data")[2]);
			returnData([ "code" => 200, "enabled" => $enabledStatus == 1]);
		break;
		// 配置TOTP —— 成功码 200 / 未登录 1000
		case "totpEnable":
			if (!$LOGIN) returnData([ "code" => 1000 ]);
			deleteFile("./.FileAdmin/totp-data");
			$secretKey = base32Encode(random_bytes(20));
			mkdir("./.FileAdmin/totp-data", 0777, true);
			file_put_contents("./.FileAdmin/totp-data/" . $secretKey, 0);
			returnData([ "code" => 200, "secret" => $secretKey]);
		break;
		// 确认TOTP —— 成功码 200 / 未登录 1000 / 验证失败 1001
		case "totpConfirm":
			if (!$LOGIN) returnData([ "code" => 1000 ]);
			$result = verifyTotp(getParam("totp"));
			if ($result) {
				file_put_contents("./.FileAdmin/totp-data/" . scandir("./.FileAdmin/totp-data")[2], 1);
				returnData([ "code" => 200 ]);
			}
			returnData([ "code" => 1001 ]);
		break;
	}
break;

// 文件下载 =====================================================================
case "download":
	if (!$_GET["name"] || !is_file("./" . $_GET['name']) || !$LOGIN) { http_response_code(400); die(); }
	$file = "./" . $_GET['name'];
	if (is_dir("./.FileAdmin/file-modify" . $_GET['name'])) $file = "./.FileAdmin/file-modify" . $_GET['name'] . "/" . scandir("./.FileAdmin/file-modify" . $_GET['name'])[2];
	$filename = end(explode("/", $_GET["name"]));
	$filesize = filesize($file);
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Content-Length: ' . $filesize);
	if (isset($_SERVER['HTTP_RANGE'])) {
		list($start, $end) = explode('-', $_SERVER['HTTP_RANGE']);
		$start = intval($start);
		$end = $end ? intval($end) : $filesize - 1;
		header('HTTP/1.1 206 Partial Content');
		header('Accept-Ranges: bytes');
		header('Content-Range: bytes ' . $start . '-' . $end . '/' . $filesize);
	} else {
		$start = 0;
		$end = $filesize - 1;
	}
	$fp = fopen($file, 'rb');
	fseek($fp, $start);
	while (!feof($fp) && ftell($fp) <= $end) {
		$buffer = fread($fp, 1024);
		echo $buffer;
		flush();
	}
	fclose($fp);
	die();
break;

// 输出样式文件 =================================================================
case "stylesheet": header("content-type: text/css"); ?> /* <style> */

	/* 公用 */
	@import "https://oss.starxw.com/fileadmin/static/font/hmsans/font.css";
	html{background:#F9F9FB;font-size:16px;user-select:none;--FilesWidth:200px;font-family:'hmsans', '微软雅黑';overflow:hidden;}
	body{margin:0;}
	font{font-family:icon;}
	*{box-sizing:border-box;scrollbar-width:none;outline:none;}
	*[hidden]{display:none!important;}
	*:not(.textEditorContainer *)::-webkit-scrollbar{display:none;}
	::selection{background:#E8F5FF;color:black;}
	a{color:#1E9FFF;text-decoration:none;cursor:default;}
	a:hover{text-decoration:underline;}
	a:active{text-decoration:underline;opacity:.8;}
	button{background:#1E9FFF;color:white;border:0;border-radius:5px;padding:5px 20px;font-size:1rem;transition:filter .2s;font-family:inherit;}
	button.sub{background:#E8F5FF;color:#1E9FFF;}
	button:hover{filter:brightness(.95);}
	button:active{filter:brightness(.9);}
	button:disabled{filter:grayscale(1)!important;}
	input:not(.textEditorContainer input),select,textarea:not(.textEditorContainer textarea),#totpForm{font-size:.95rem;padding:10px;width:100%;border-radius:5px;border:1px solid rgba(0,0,0,.05);background:#F9F9FB;transition:border .2s,background .2s;margin:15px 0;caret-color:#1E9FFF;font-family:inherit;}
	input:not(.textEditorContainer input):focus,select:hover,textarea:not(.textEditorContainer textarea):focus,#totpForm:focus-within{background:white;border:1px solid #1E9FFF;}
	textarea{font-family:inherit;resize:vertical;}
	div.checkbox{transition:all .2s;display:flex;align-items:center;text-align:left;font-size:.9em;color:grey;}
	div.checkbox.checked{color:#1E9FFF;}
	div.checkbox::before{content:'\EB7B';font-family:'icon';background:rgba(252,252,252,.8);color:transparent;border-radius:5px;width:15px;height:15px;display:flex;align-items:center;justify-content:center;border:1px solid rgba(0,0,0,.05);transition:all .2s;margin-right:5px;font-size:.6em;padding-top:1px;}
	div.checkbox.checked::before{background:#1E9FFF!important;border:1px solid #1E9FFF!important;color:white;zoom:1;font-size:.8em;}

	/* 顶栏 */
	header{height:45px;border-bottom:1px solid rgba(0,0,0,.05);position:fixed;top:0;left:0;width:100%;display:flex;align-items:center;padding:10px;background:#F9F9FB;white-space:nowrap;z-index:5;}
	header .branding{font-size:1.5em;}
	header .branding span:nth-child(2){color:#1E9FFF;font-weight:bold;}
	header #version{background:#F5F5F5;color:#CDCDCD;font-size:.7em;padding:0 5px;border-radius:10px 10px 10px 0;margin:0 0 15px 5px;}
	header .seperator{flex:100%;}
	header>font{font-size:1.3em;padding:2.5px;}
	header>font:hover{color:#1E9FFF;}
	header>font:active{color:#1E9FFF;opacity:.8;}
	header .active{padding:2px 10px;border-radius:20px;color:#1E9FFF;background:#E8F5FF;font-size:.9em;margin-right:5px;}
	header .active:hover{background:#1E9FFF;color:white;}
	header .active:active{background:#1E9FFF;color:white;opacity:.8;}

	/* 主体 */
	main>div{position:fixed;top:45px;bottom:0;background:white;overflow:hidden;}
	main>#files{width:var(--FilesWidth);left:0;z-index:3;transition:all .2s,width 0s;border-right:1px solid rgba(0,0,0,.05);}
	main>#tabs{width:calc(100% - var(--FilesWidth));right:0;transition:all .2s;}
	main>#resizer{left:calc(var(--FilesWidth) - 4px);width:8px;cursor:col-resize;opacity:0;z-index:4;}
	main>#files:not(div:focus-within) #fileHeader,main>#files:not(div:focus-within) #fileAddress,main>#tabs:not(div:focus-within) #tabsSwitcher{opacity:.8;}
	.fullMode main>#tabs{width:100%;border-radius:0;}
	.fullMode main>#files{z-index:0;opacity:0;left:calc(0px - var(--FilesWidth));}
	#fullModeBtn .icon{transition:transform .2s;}
	.fullMode #fullModeBtn .icon{transform:scaleX(-1);}
	.fullMode #resizer{display:none;}

	/* 消息框 */
	#msgBoxContainer{position:fixed;top:60px;right:15px;width:250px;z-index:25;}
	#msgBoxContainer .msgBox{margin-bottom:5px;padding:10px;border:1px solid rgba(0,0,0,.05);transition:all .2s;background:#F9F9FB;display:flex;align-items:center;position:relative;box-shadow:0 4px 6px rgba(0,0,0,.04);border-radius:5px;overflow:hidden;}
	#msgBoxContainer .msgBox .close{position:absolute;top:10px;right:10px;opacity:0;}
	#msgBoxContainer .msgBox:hover .close{opacity:1;}
	#msgBoxContainer .msgBox .close:hover{color:#1E9FFF;}
	#msgBoxContainer .msgBox .close:active{color:#1E9FFF;opacity:.8;}
	#msgBoxContainer .msgBox.hidden{transform:translateX(300px);opacity:0;height:0!important;margin-bottom:0;padding:0 10px;border-width:0 1px;}
	#msgBoxContainer .msgBox .icon::before{color:#5D5D5D;content:'\EE59';font-size:2em;transition:color .2s;margin-right:7px;}
	#msgBoxContainer .msgBox>div>div{font-size:.8em;line-height:1.1em;margin:.1em 0;}
	#msgBoxContainer .msgBox.success{background:#EFFAE5;}
	#msgBoxContainer .msgBox.success .icon::before{color:#00B100;content:'\EB81';}
	#msgBoxContainer .msgBox.error{background:#FFF2F2;}
	#msgBoxContainer .msgBox.error .icon::before{color:#FF0000;content:'\ECA1';}
	#msgBoxContainer .msgBox.loading{background:#E8F5FF;}
	#msgBoxContainer .msgBox.loading .icon::before{color:#1E9FFF;content:'\F33C';animation:twinkle 1s linear infinite;}
	@keyframes twinkle{0%{opacity:1;}50%{opacity:.5;}100%{opacity:1;}}

	/* 对话框 */
	.dialogContainer{position:fixed;top:0;left:0;height:100%;width:100%;z-index:20;background:rgba(0,0,0,.2);}
	.dialogContainer>.dialog{position:absolute;top:0;left:0;bottom:0;right:0;width:fit-content;min-width:320px;max-width:500px;height:fit-content;margin:auto;background:#F9F9FB;border:1px solid rgba(0,0,0,.05);border-radius:7px;padding:20px;box-shadow:0 4px 6px rgba(0,0,0,.04);overflow:hidden;animation:dialogShow .2s;}
	.dialogContainer>.dialog>.title{font-size:1.3em;font-weight:bold;}
	.dialogContainer>.dialog>.content{margin:8px 0 16px 0;max-width:calc(100vw - 60px);font-size:.95em;position:relative;}
	.dialogContainer>.dialog>.content>input{margin:0;margin-top:10px;}
	.dialogContainer>.dialog>.content>.fileAddressBar{background:white;border-radius:5px;margin-bottom:5px;margin-top:10px;height:30px;}
	.dialogContainer>.dialog>.content>.fileAddressBar>div{width:100%;font-size:.95em;padding:0 10px;}
	.dialogContainer>.dialog>.content>.fileList{background:white;height:300px;border-radius:5px;min-width:300px;}
	.dialogContainer>.dialog>.content>.fileList>div{padding:0 10px;font-size:.9em;height:30px;}
	.dialogContainer>.dialog>.content>.fileList>center{margin:110px 0;}
	.dialogContainer>.dialog>.content>.fileList>center>font{color:black;opacity:.6;}
	.dialogContainer>.dialog>.content>.fileLoadingLayer{position:absolute;top:35px;height: calc(100% - 35px);left:0;width:100%;}
	.dialogContainer>.dialog>.content.dirPickerContent{max-width:calc(100vw - 60px);width:400px;}
	.dialogContainer>.dialog>.buttons{text-align:right;}
	.dialogContainer>.dialog>.buttons>button{margin-left:5px;height:30px;vertical-align:top;padding:0 20px;}
	.dialogContainer>.dialog>.buttons>button.icoOnly{padding:0 10px;}
	@keyframes dialogShow{from{opacity:0;transform:scale(.9);}to{opacity:1;transform:none;}}

	/* 窗口 */
	#tabsSwitcher{height:30px;background:#F9F9FB;overflow-x:scroll;overflow-y:hidden;font-size:0;white-space:nowrap;padding:0 5px 0 0;}
	#tabsSwitcher>div{vertical-align:top;}
	#tabsSwitcher .tab{height:30px;padding:0 8px 0 10px;font-size:14px;position:relative;margin-left:-1px;display:inline-flex;justify-content:center;align-items:center;opacity:.8;}
	#tabsSwitcher .tab:hover{opacity:1;}
	#tabsSwitcher .tab::after{position:absolute;top:0;bottom:0;right:0;height:20px;margin:auto 0;border-left:1px solid rgba(0,0,0,.05);content:'';}
	#tabsSwitcher .tab.active::after{opacity:0;}
	#tabsSwitcher .tab.button{padding:0 10px;opacity:1;}
	#tabsSwitcher .tab.button font{margin:0;}
	#tabsSwitcher .tab.button:hover{color:#1E9FFF;}
	#tabsSwitcher .tab.button:active{color:#1E9FFF;filter:brightness(.95);}
	#tabsSwitcher .tab.active{background:white;opacity:1;border-top:1.5px solid #1E9FFF;}
	#tabsSwitcher .tab .title{padding-bottom:1px;}
	#tabsSwitcher .tab .icon{margin:1.5px 5px 0 0;}
	#tabsSwitcher .tab .close{margin-left:10px;opacity:.8;}
	#tabsSwitcher .tab .close:hover{opacity:1;color:#1E9FFF;}
	#tabsSwitcher .tab .close:active{opacity:.8;}
	#tabsSwitcher #userTabsContainer{display:inline-block;}
	#tabsContent{position:absolute;top:30px;left:0;width:100%;height:calc(100% - 30px);}
	#tabsContent>div{position:absolute;top:0;left:0;width:100%;height:100%;overflow:hidden;display:none;}
	#tabsContent>div.active{display:block;}
	#tabsContent .tabLayer{position:absolute;top:0;left:0;width:100%;height:100%;background:white;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;}
	#tabsContent .tabLayer>font{font-size:4em;}
	#tabsContent .tabLayer>.title{font-size:1.4em;margin:5px 0;}
	#tabsContent .tabLayer>.description{opacity:.8;font-size:.9em;margin-bottom:10px;}
	#tabsContent .tabLayer>a{display:block;margin-bottom:5px;}
	#workspace{height:100vh;width:100vw;}

	/* 登录框 */
	.login{margin:auto;width:fit-content;height:fit-content;left:0;right:0;top:8px;padding:20px;width:340px;border-radius:7px;border:1px solid rgba(0,0,0,.05);}
	.login>font{display:block;font-size:5em;text-align:center;color:#1E9FFF;}
	#loginFormUser{display:none;}
	#loginFormPassword{width:100%;text-align:center;}
	#loginFormButton{width:100%;}
	#totpForm{position:relative;height:50px;display:flex;padding:0;}
	#totpForm>div{width:100%;height:100%;position:relative;font-size:2em;display:flex;align-items:center;justify-content:center;font-family:'Consolas',monospace;}
	#totpForm>div:not(div:first-child)::after{content:"";position:absolute;left:0;top:10px;bottom:10px;border-left:1px solid #DADADA;}
	#totpForm>input{position:absolute;top:0;left:0;width:100%;height:100%;opacity:0;margin:0;}
	#totpBox p{text-align:center;}


	/* 窗口内容 */
	.tabFrame{position:absolute;top:0;left:0;width:100%;height:100%;border:0;color-scheme:light;}
	/* 欢迎页 */
	.welcome{padding:10vh 10vw;max-height:100%;overflow-y:scroll;}
	.welcome .greeting img{width:80px;height:80px;margin-bottom:15px;}
	.welcome .greeting div b{font-weight:normal;font-size:2em;margin-top:-5px;display:block;}
	.welcome .greeting div div{opacity:.8;}
	.welcome .welcomeLinks{margin-top:30px;}
	.welcome .welcomeLinks a{margin-top:10px;display:block;}
	/* 文本编辑器 */
	.textEditorContainer{height:calc(100% - 25px);position:relative;}
	.textEditorContainer *{scrollbar-width:thin;}
	.textEditorContainer .scroll-decoration{display:none;}
	.textEditorContainer textarea{position:absolute;width:100%;height:100%;border:0;padding:15px 15px 50vh 15px;resize:none;font-family:"宋体","Source Code Pro","Consolas",monospace;}
	.editorStatusBar{display:flex;align-items:center;height:25px;background:#F9F9FB;font-size:.8em;white-space:nowrap;overflow-x:scroll;}
	.editorStatusBar>div{display:flex;align-items:center;padding:0 10px;height:100%;position:relative;}
	.editorStatusBar>div:not(.unclickable):hover{background:#F0F0F0;color:#1E9FFF;}
	.editorStatusBar>div:not(.unclickable):active{background:#F0F0F0;color:#1E9FFF;filter:brightness(1.02)}
	.editorStatusBar>div>span{margin-left:5px;padding-bottom:1px;}
	.editorStatusBar>div>select{opacity:0;padding:0;margin:0;position:absolute;top:0;left:0;width:100%;height:100%;}
	.editorStatusBar>.mobileInputBtn{display:none;}
	.editorMobileInput{position:absolute;width:100%;bottom:30px;padding:2.5px;background:#F9F9FB;z-index:114514;display:none;border-bottom:1px solid white;}
	.editorMobileInput>.keyboardMain{display:grid;grid:auto-flow/repeat(10,10%);height:30px;transition:height .2s;overflow:hidden;font-family:'Consolas',monospace;}
	.editorMobileInput.unfold>.keyboardMain{height:90px;}
	.editorMobileInput>.keyboardMain>div{height:25px;border-radius:5px;margin:2.5px;display:flex;align-items:center;justify-content:center;font-size:.9em;}
	.editorMobileInput>.keyboardMain>div:active{color:#1E9FFF;}
	#ace_settingsmenu{display:none;}
	/* 媒体播放器 */
	.mediaPlayer{height:100%;width:100%;background:white;}
	.audioIcon{position:absolute;top:0;bottom:50px;left:0;right:0;margin:auto;height:fit-content;width:fit-content;display:block;font-size:10em;color:#1E9FFF;pointer-events:none;}
	/* 图像查看器 */
	.imageViewer{height:100%;width:100%;object-fit:contain;}
	/* 打开方式 */
	.openWithContainer .container{background:#F9F9FB;border:1px solid #DADADA;border-radius:5px;width:300px;padding:10px 0;margin-top:10px;max-height:calc(100% - 300px);overflow-y:scroll;}
	.openWithContainer .container>div{display:flex;padding:10px 20px;align-items:center;white-space:nowrap;text-overflow:ellipsis;transition:all .2s;}
	.openWithContainer .container>div:hover{color:#1E9FFF;}
	.openWithContainer .container>div:active{color:#1E9FFF;background:#E8F5FF;}
	.openWithContainer .container>div.active{background:#1E9FFF!important;color:white;}
	.openWithContainer .container>div>font{background:rgba(255,255,255,.1);font-size:1.5em;width:40px;height:40px;border-radius:5px;display:flex;align-items:center;justify-content:center;margin-right:10px;}
	.openWithContainer .container>div>span{font-size:1.2em;text-align:left;}
	.openWithContainer .container>div>span>small{font-size:.6em;opacity:.8;display:block;margin-top:-2px;}
	.openWithContainer .container>a{margin-top:10px;display:block;}
	.openWithContainer .btns{margin-top:20px;display:flex;align-items:center;white-space:nowrap;width:300px;}
	.openWithContainer .btns>.checkbox{width:100%;}
	.openWithContainer:not(.full) .container>div.folded{display:none;}
	.openWithContainer.full .container>a{display:none;}
	/* 设置 */
	.settingsContainer{padding:20px 5%;overflow-y:scroll;height:100%;}
	.settingsContainer>.title{font-size:2em;text-align:center;border-bottom:1px solid rgba(0,0,0,.05);padding:10px 0;}
	.settingsContainer>.config{padding:20px 5%;border-bottom:1px solid rgba(0,0,0,.05);}
	.settingsContainer>.config>.title{font-size:1.2em;margin-bottom:3px;}
	.settingsContainer>.config>.description{font-size:.9em;opacity:.8;line-height:1.1em;}
	.settingsContainer>.config input,.settingsContainer>.config select,.settingsContainer>.config button,.settingsContainer>.config textarea{padding:5px 10px;width:300px;margin-bottom:0;margin-top:10px;height:100%;max-width:100%;}
	.settingsContainer>.config>textarea{height:200px;resize:none;white-space:nowrap;font-family:'Consolas',monospace,'宋体';font-size:.8em;tab-size:4;}
	.settingsContainer>.config>select>option{font-size:.9rem;}
	.settingsContainer>.footer{margin:15px 0;text-align:center;}
	.totpQrcode{background:#F9F9FB;padding:20px;display:flex;align-items:center;justify-content:center;color:grey;border-radius:5px;width:300px;height:300px;margin:10px 0 20px 0;border:1px solid #DADADA;}
	/* 目录分析 */
	.analyseContainer{padding:20px;overflow-y:scroll;height:100%;}
	.analyseContainer>.title{font-size:2em;text-align:center;padding:10px 0 20px 0;}
	.analyseContainer>.box{background:#F9F9FB;margin-bottom:10px;border-radius:5px;padding:20px;}
	.analyseContainer>.box .title{font-size:1.2em;margin-bottom:10px;}
	.analyseContainer>.box .progress{background:white;position:relative;height:15px;border-radius:10px;overflow:hidden;}
	.analyseContainer>.box .progress>div{background:#1E9FFF;height:100%;min-width:1%;position:absolute;top:0;left:0;}
	.analyseContainer>.box .progress>div:nth-child(2){opacity:.2;}
	.analyseContainer>.box .progress.file{border-radius:5px;margin-top:10px;height:fit-content;}
	.analyseContainer>.box .progress.file>div:nth-child(1){background:transparent;width:100%;padding:10px 15px;white-space:nowrap;text-overflow:ellipsis;overflow:hidden;position:static;}
	.analyseContainer>.box .dataContainer{opacity:.8;font-size:.8em;margin-top:5px;display:flex;align-items:center;}
	.analyseContainer>.box .dataContainer>span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:100%;text-align:center;}
	.analyseContainer>.box .dataContainer>span:first-child{text-align:left;}
	.analyseContainer>.box .dataContainer>span:last-child{text-align:right;}
	/* 文件搜索 */
	.searchContainer{padding:20px;overflow-y:scroll;height:100%;}
	.searchContainer>.title{font-size:2em;text-align:center;border-bottom:1px solid #EBEBEB;padding:10px 0;}
	.searchContainer>.searchOptions{padding:20px 5%;}
	.searchContainer>.searchOptions>div{display:flex;align-items:center;margin-bottom:10px;}
	.searchContainer>.searchOptions>div>span{min-width:120px;}
	.searchContainer>.searchOptions>div>div{flex:100%;display:flex;align-items:center;border-radius: 5px;border:1px solid rgba(0,0,0,.05);background:#F9F9FB;transition:border .2s,background .2s;}
	.searchContainer>.searchOptions>div>div:focus-within{background:white;border:1px solid #1E9FFF;}
	.searchContainer>.searchOptions>div>div>input,.searchContainer>.searchOptions>div>div>select{background:transparent!important;border:none!important;margin:0;}
	.searchContainer>.searchOptions>div>div>font{padding:10px;}
	.searchContainer>.searchOptions>div>div>font:hover{color:#1E9FFF;}
	.searchContainer>.searchOptions>div>div>font:active{color:#1E9FFF;opacity:.8;}
	.searchContainer>.searchOptions>div>button{flex:100%;}
	.searchContainer>.searchOptions>.btnContainer{margin-top:20px;}
	.searchContainer>.searchOptions>.btnContainer>span{min-width:10px;}
	.searchContainer>.searchResult{background:#F9F9FB;margin-bottom:10px;border-radius:5px;padding:10px;margin:0 5%;}
	.searchContainer>.searchResult>center{padding:50px 0;}
	.searchContainer>.searchResult>div{border-top:1px solid white;padding:10px;}
	.searchContainer>.searchResult>div:first-child{border-top:none!important;}
	.searchContainer>.searchResult>div>.name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
	.searchContainer>.searchResult>div>.name:hover{color:#1E9FFF;}
	.searchContainer>.searchResult>div>.name:active{color:#1E9FFF;opacity:.8;}
	.searchContainer>.searchResult>div>.lines{margin-top:5px;font-family:'Consolas',monospace,'宋体';font-size:.85em;background:#EFEFEF;border-radius:5px;padding:5px;}
	.searchContainer>.searchResult>div>.lines>div{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
	.searchContainer>.searchResult>div>.lines>div>span:first-child{font-weight:bold;text-align:right;width:35px;display:inline-block;margin-right:10px;}
	.searchContainer>.searchResult>div>.lines>div>span:last-child{user-select:text;}
	/* 捐赠 */
	.donateImage{margin-top:20px;width:600px;max-width:calc(100% - 20px);}

	/* 文件管理 */
	#fileHeader{background:#F9F9FB;height:30px;display:flex;align-items:center;padding:0 10px;white-space:nowrap;}
	#fileHeader .seperator{flex:100%;}
	#fileHeader span{font-size:.85em;}
	#fileHeader font{padding:0 0 0 5px;font-size:1em;}
	#fileHeader font:hover{color:#1E9FFF;}
	#fileHeader font:active{opacity:.8;}
	#fileAddress{background:#F9F9FB;height:30px;padding:0 5px 5px 5px;}
	.fileAddressBar{background:white;height:24px;border-radius:5px;white-space:nowrap;font-size:.9em;}
	.fileAddressBar>*{vertical-align:top;display:inline-flex;align-items:center;height:100%;}
	.fileAddressBar>font{width:25px;justify-content:center;border-right:1px solid rgba(0,0,0,.05);text-align:center;}
	.fileAddressBar>font:hover{color:#1E9FFF;}
	.fileAddressBar>font:active{color:#1E9FFF;backdrop-filter:brightness(.98);}
	.fileAddressBar>div{width:calc(100% - 30px);padding:0 7px;font-size:.9em;overflow-x:scroll;}
	.fileAddressBar>div>font{margin:0 2px -2px 2px;opacity:.5;}
	.fileAddressBar>div>div{padding:2px 0;}
	.fileAddressBar>div>div:hover{color:#1E9FFF;}
	.fileAddressBar>div>div:active{color:#1E9FFF;opacity:.8;}
	.fileList{height:calc(100% - 60px);overflow-y:scroll;}
	.fileList>div{padding:0 8px;display:flex;align-items:center;white-space:nowrap;font-size:.8em;height:25px;}
	.fileList>div:hover{background:#F9F9FB;}
	.fileList>div:active{filter:brightness(.98);}
	.fileList>div.editorActive{background:#E8F5FF;}
	.fileList>div.selected{background:#1E9FFF;color:white;}
	.fileList>div font{min-width:1.3em;display:block;}
	.fileList>div .name{flex:100%;padding:0 5px;overflow:hidden;text-overflow:ellipsis;}
	.fileList>div .size{opacity:.5;}
	.fileList>center{margin:50px 0;font-size:.9em;}
	.fileList>center>font{display:block;font-size:3em;color:#1E9FFF;}
	#refreshIndicator{display:flex;align-items:center;justify-content:center;height:0;background:#F9F9FB;transition:height .3s ease-in-out;overflow:hidden;max-height:200px;font-size:.85em;}
	#fileLoading{position:absolute;top:60px;height:calc(100% - 60px);left:0;width:100%;background:rgba(255,255,255,.5);}
	#fileSelectionBox{position:fixed;background-color:#1E9FFF;pointer-events:none;opacity:.2;border:1px solid white;}
	#fileMenu{position:fixed;width:230px;overflow-y:scroll;padding:7px 0;border-radius:7px;display:none;background:rgba(255,255,255,.8);backdrop-filter:blur(20px);border:1px solid rgba(0,0,0,.05);box-shadow:0 4px 6px rgba(0,0,0,.04);}
	#fileMenu.active{animation:fileMenuShow .2s;display:block;}
	@keyframes fileMenuShow{from{opacity:0;transform:scaleX(.9) scaleY(.6);padding:0;}to{opacity:1;transform:none;padding:7px 0;}}
	#fileMenu>div>div{padding:0 15px;height:28px;display:flex;align-items:center;font-size:.9em;}
	#fileMenu>div>div.alert{color:red;}
	#fileMenu>div>div:hover{background:rgba(0,0,0,.02);}
	#fileMenu>div>div:active{background:rgba(0,0,0,.04);}
	#fileMenu>div>div>span{display:block;margin:0 5px;flex:100%;padding-bottom:1px;}
	#fileMenu>div>div>small{opacity:.8;}
	#fileMenu>div>span{display:block;margin:7px 0;border-top:1px solid rgba(0,0,0,.05);}
	.filePropTable{display:block;max-width:100%;overflow:hidden;}
	.filePropTable .row{white-space:nowrap;display:flex;align-items:center;margin:1px 0;}
	.filePropTable .row>span:first-child{font-weight:bold;width:100px;min-width:100px;}
	.filePropTable .row>span:last-child{opacity:.9;overflow:hidden;text-overflow:ellipsis;user-select:text;}
	.filePropTable .seperator{border-top:1px solid rgba(0,0,0,.05);margin:7px 0 5px 0;}

	/* 文件上传 */
	.filesContainer{padding:20px;overflow-y:scroll;height:100%;}
	.filesContainer>div{border-radius:5px;margin-bottom:10px;padding:10px;position:relative;overflow:hidden;}
	.filesContainer>div>.name{font-size:1.1em;font-weight:bold;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
	.filesContainer>div>.info{font-size:.8em;opacity:.8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:5px;}
	.filesContainer>div>.info>div{display:inline-block;background:white;margin-right:5px;border-radius:3px;padding:2px 7px;}
	.filesContainer>div>.background{position:absolute;top:0;left:0;height:100%;width:100%;background:#F9F9FB;z-index:-2;}
	.filesContainer>div>.progressBar{position:absolute;top:0;left:0;height:100%;background:#E8F5FF;transition:width .5s,background .2s;z-index:-1;width:0%;}
	.filesContainer>div.success>.progressBar{width:100%!important;background:#EFFAE5;z-index:-1;}
	.filesContainer>div.error>.progressBar{width:100%!important;background:#EFFAE5;z-index:-1;background:#FFF2F2;}
	#fileUploadTipContainer{position:fixed;top:0;left:0;height:100%;width:100%;z-index:6;background:rgba(0,0,0,.1);border:0;border-radius:0;pointer-events:none;opacity:0;transition:opacity .2s;}
	#fileUploadTipContainer #fileUploadTip{position:fixed;padding:5px 10px;border-radius:7px;background:white;border:1px solid rgba(0,0,0,.05);box-shadow:0 4px 6px rgba(0,0,0,.04);pointer-events:none;font-size:.9em;height:fit-content;width:200px;}
	#fileUploadTipContainer #fileUploadTip>font{color:#1E9FFF;}
	.uploadFileDrag #fileUploadTipContainer{opacity:1;}

	/* 深色模式 */
	.dark{background:#323232;color:#F0F0F0;color-scheme:dark;}
	.dark *::selection{background:#1E9FFF;color:white;}
	.dark .msgBox{color:black;}
	.dark input:not(.textEditorContainer input),.dark select,.dark #totpForm{background:#1E1E1E;border:1px solid #323232;color:white;}
	.dark input:not(.textEditorContainer input):focus,.dark select:hover,.dark #totpForm:focus-within{border:1px solid #1E9FFF;}
	.dark textarea:not(.textEditorContainer textarea){background:#1E1E1E!important;border:1px solid #323232!important;color:white;}
	.dark textarea:not(.textEditorContainer textarea):focus{border:1px solid #1E9FFF!important;}
	.dark .textEditorContainer textarea{background:#1E1E1E;}
	.dark button.sub{background:#062033;}
	.dark div.checkbox::before{border:1px solid #323232;background:#1E1E1E;}
	.dark .dialogContainer{background:rgba(0,0,0,.3);}
	.dark .dialogContainer .dialog{background:#1E1E1E;border:1px solid #323232;}
	.dark .dialogContainer>.dialog>.content>.fileAddressBar,.dark .dialogContainer>.dialog>.content>.fileList{background:#1E1E1E;border:1px solid #323232;}
	.dark .dialogContainer>.dialog>.content>.fileList>center>font{color:white;}
	.dark header{background:black;border-bottom:1px solid #323232;}
	.dark header #version{background:#323232;}
	.dark header .active{background:#062033;color:#F0F0F0;}
	.dark header .active:hover,.dark header .active:active{background:#061E30;}
	.dark main>div{background:#1E1E1E;border-color:#323232!important;}
	.dark #tabsSwitcher{background:black;}
	.dark #tabsSwitcher .tab:hover{background:transparent;}
	.dark #tabsSwitcher .tab.active{background:#1E1E1E;}
	.dark #tabsSwitcher .tab::after{border-left:1px solid black;}
	.dark #fileHeader,.dark #fileAddress,.dark #refreshIndicator{background:black;}
	.dark .fileAddressBar{background:#1E1E1E;}
	.dark .fileAddressBar>font{border-right:1px solid #1E1E1E;}
	.dark #fileLoading{background:rgba(0,0,0,.5);}
	.dark #fileMenu{background:rgba(0,0,0,.8);border:1px solid #323232;}
	.dark #fileMenu>div>div:hover{background:#323232;}
	.dark #fileMenu>div>span{border-top:1px solid #323232;}
	.dark .fileList>div:hover{background:#323232;}
	.dark .fileList>div.selected{background:#1E9FFF;}
	.dark .fileList>div.editorActive{background:#062033;}
	.dark .filePropTable .seperator{border-top:1px solid #323232;margin:7px 0 5px 0;}
	.dark #fileUploadTipContainer #fileUploadTip{border:1px solid #323232;background:rgba(0,0,0,.8);}
	.dark #tabsContent .tabLayer{background:#1E1E1E;}
	.dark .openWithContainer .container{border:1px solid #323232;background:#1E1E1E;}
	.dark .openWithContainer .container>div:active{background:#062033;}
	.dark .settingsContainer>.config,.dark .settingsContainer>.title{border-bottom:1px solid #323232;}
	.dark #totpForm>div:not(div:first-child)::after{border-left:1px solid #323232;}
	.dark .totpQrcode{background:#1E1E1E;border:1px solid #323232;}
	.dark .editorStatusBar{background:#1E1E1E;}
	.dark .editorStatusBar>div:not(.unclickable):hover,.dark .editorStatusBar>div:not(.unclickable):active{background:black;}
	.dark .editorMobileInput{background:#1E1E1E;border-bottom:1px solid #323232;}
	.dark .mediaPlayer{background:black;}
	.dark .filesContainer>div>.background{background:#323232;}
	.dark .filesContainer>div>.progressBar{opacity:.1;}
	.dark .filesContainer>div>.info>div{background:black;}
	.dark .searchContainer>.title{border-bottom:1px solid #323232;}
	.dark .searchContainer>.searchOptions>div>div{background:#1E1E1E;border:1px solid #323232;color:white;}
	.dark .searchContainer>.searchOptions>div>div:focus-within{border:1px solid #1E9FFF;}
	.dark .searchContainer>.searchOptions>div>div>select>option{background:black;}
	.dark .searchContainer>.searchResult{background:black;}
	.dark .searchContainer>.searchResult>div>.lines{background:#1E1E1E;}
	.dark .searchContainer>.searchResult>div{border-top:1px solid #323232;}
	.dark .analyseContainer>.box{background:#1E1E1E;}
	.dark .analyseContainer>.box .progress{background:black;}

	/* 移动适配 */
	@media screen and (max-width: 600px){
		html{--FilesWidth:100vw;}
		main>#tabs{width:100%;right:-100%;z-index:4;}
		main>#tabs,main>#files{top:45px;bottom:0;right:0;width:100vw;border-radius:0;border:0px solid rgba(0,0,0,.05);}
		main>#files{opacity:1!important;left:0!important;}
		#fileHeader,#fileAddress,#tabsSwitcher{opacity:1!important;}
		header{border-bottom:1px solid #F0F0F0;}
		.dark header{border-bottom:1px solid transparent;}
		html:not(.fullMode) main>#tabs{right:calc(0px - 100vw - 1px);}
		.fileList{height:calc(100% - 100px);}
		.fileList>div{padding:0 10px;font-size:.9em;height:35px;}
		.fileList>div.editorActive{background:transparent!important;}
		#fileMenu{display:block!important;top:calc(100% - 40px)!important;left:0!important;animation:none!important;margin:0;height:40px;border-radius:0;border:0!important;background:#F9F9FB;width:100%;padding:0;text-align:center;font-size:0;overflow-x:scroll;white-space:nowrap;}
		.dark #fileMenu{background:black!important;}
		#fileMenu>div{height:100%;zoom:.9;}
		#fileMenu>div>div{display:inline-flex;vertical-align:top;padding:0;width:65px;height:100%;font-size:10px;flex-direction:column;justify-content:center;}
		#fileMenu>div>div>span{margin:0;height:fit-content;white-space:nowrap;flex:unset;opacity:.8;}
		#fileMenu>div>div>font{display:block;font-size:1.8em;}
		#fileMenu>div>div>small{display:none;}
		#fileMenu>div>span{display:none;}
		.mobileDeviceHidden,#pcMenuBtn{display:none!important;}
		#mobilePageBtn{display:inline-block!important;}
		.textEditorContainer{height:calc(100% - 30px);}
		.editorMobileInput{display:block;}
		.editorStatusBar>.mobileInputBtn{display:flex;}
		.editorStatusBar{height:30px;}
	}

/* </style> */ <?php break;

// 输出脚本文件 =================================================================
case "javascript": header("content-type: text/javascript"); ?> /* <script> */

	function request(data) {
		return new Promise(async (resolve, reject) => {
			try {
				let jsonData = JSON.stringify(data);
				let base64Data = base64(jsonData);
				const response = await fetch(
					"?run=backend&stamp=" + new Date().getTime(), 
					{ method: "POST", headers: {'content-type': 'application/x-www-form-urlencoded'}, body: "data=" + encodeURIComponent(base64Data) 
				});
				if (!response.ok) reject();
				const responseData = await response.json();
				resolve(responseData);
			} catch (error) {
				console.warn(error);
				reject(error);
			}
		});
	}
	function textEscape(raw) {
		if(!raw) return "";
		let toolDiv = document.createElement("div");
		toolDiv.innerText = raw;
		let text = toolDiv.innerHTML;
		toolDiv.remove();
		return text;
	}
	function ID(id) { return document.getElementById(id); }
	function loadRemoteScript(url, onload, onerror) {
		let isLoaded = false;
		Array.from(document.getElementsByTagName("script")).forEach(script => {
			if (script.src == url) {
				if (onload) onload();
				isLoaded = true;
			}
		});
		if (isLoaded) return;
		let script = document.createElement("script");
		script.src = url;
		script.onload = onload;
		script.onerror = onerror;
		document.body.appendChild(script);
	}
	function base64(text) {
		return btoa(unescape(encodeURIComponent(text)));
	}
	function handleAuthError(boxId = createMsgBox()) {
		updateMsgBox(boxId, "登录失效", "您的登录已失效，请重新登录", "error", 3000, true);
		getLogin(true);
	}
	
	// 全局变量
	let Globals = {
		onlineServiceHost: "<?php echo $HOST; ?>",
	};
	let staticHosts = {
		"Starxn Cloud": {
			monaco: "https://oss.starxw.com/fileadmin/static/monaco/vs",
			ace: "https://oss.starxw.com/fileadmin/static/ace/",
			remixicon: "https://oss.starxw.com/fileadmin/static/remixicon/remixicon.woff2",
			qrcode: "https://oss.starxw.com/fileadmin/static/qrcode/qrcode.min.js",
		},
		"ZStatic.net": {
			monaco: "https://s4.zstatic.net/ajax/libs/monaco-editor/0.45.0/min/vs",
			ace: "https://s4.zstatic.net/ajax/libs/ace/1.32.3/",
			remixicon: "https://s4.zstatic.net/ajax/libs/remixicon/4.0.0/remixicon.woff2",
			qrcode: "https://s4.zstatic.net/ajax/libs/qrcodejs/1.0.0/qrcode.min.js",
		},
		"JSDelivr Default": {
			monaco: "https://cdn.jsdelivr.net/npm/monaco-editor@latest/min/vs",
			ace: "https://cdn.jsdelivr.net/npm/ace-builds@latest/src-min-noconflict/",
			remixicon: "https://cdn.jsdelivr.net/npm/remixicon@latest/fonts/remixicon.woff2",
			qrcode: "https://cdn.jsdelivr.net/npm/qrcodejs@latest/qrcode.min.js",
		},
		"JSDelivr Fastly": {
			monaco: "https://fastly.jsdelivr.net/npm/monaco-editor@latest/min/vs",
			ace: "https://fastly.jsdelivr.net/npm/ace-builds@latest/src-min-noconflict/",
			remixicon: "https://fastly.jsdelivr.net/npm/remixicon@latest/fonts/remixicon.woff2",
			qrcode: "https://fastly.jsdelivr.net/npm/qrcodejs@latest/qrcode.min.js",
		},
		"BootCDN": {
			monaco: "https://cdn.bootcdn.net/ajax/libs/monaco-editor/0.43.0/min/vs",
			ace: "https://cdn.bootcdn.net/ajax/libs/ace/1.24.2/",
			remixicon: "https://cdn.bootcdn.net/ajax/libs/remixicon/3.5.0/remixicon.woff2",
			qrcode: "https://cdn.bootcdn.net/ajax/libs/qrcodejs/1.0.0/qrcode.min.js",
		}
	}
	

	// 初始化
	function init(silent){
		Globals = {
			tabs: [],
			editors: [],
			fileTabs: [],
			currentDir: "/",
			upload: {},
			selectedFiles: [],
			isSelecting: false,
			selectionStartX: null,
			selectionStartY: null,
			dialogCallbacks: {},
			dialogActiveElement: {},
			dirPickerResults: {},
			extLoadedState: Globals.extLoadedState??[],
			extRuntimeInfomation: {},
			extRuntime: Globals.extRuntime ?? {version: 4, editorStatusBar: [], customFileMenu: [], blockEditorLoad: 0, textEditorLoadTries: 0, extHooks: {}},
			isTouchDevice: !matchMedia("(pointer:fine)").matches,
			isMobileDevice: /Android|iPhone|iPad|iPod/.test(navigator.userAgent),
			currentVersion: Globals.currentVersion,
			onlineServiceHost: Globals.onlineServiceHost,
		};
		ID("userTabsContainer").innerHTML = "";
		ID("tabsContent").innerHTML = "";
		if(!silent) ID("msgBoxContainer").innerHTML = "";
		getLogin(silent);
		document.documentElement.oncontextmenu = e => { e.preventDefault(); };
		document.documentElement.onpointerdown = hideFileMenu;
		document.body.onblur = hideFileMenu;
		document.documentElement.onkeydown = e => {
			hideFileMenu();
			if (["tab", "f5", "f12"].includes(e.key.toLowerCase())) e.preventDefault();
			if (["s", "r", "w", "n", "f"].includes(e.key.toLowerCase()) && e.ctrlKey) e.preventDefault();
		}
		document.documentElement.onmousewheel = e => { hideFileMenu(); }
		document.body.onresize = ()=> {  hideFileMenu(); editorData[getConfig("textEditor")].resize(); };
		document.documentElement.style.setProperty("--FilesWidth", getConfig("filePanelWidth") + "px");
		ID("workspace").ondragover = e => {
			e.preventDefault();
			if (e.dataTransfer.types.includes("Files")) {
				ID("workspace").classList.add("uploadFileDrag");
				ID("fileUploadTip").style.left = e.clientX+10>document.documentElement.clientWidth-200?document.documentElement.clientWidth-200:e.clientX+10 + "px";
				ID("fileUploadTip").style.top = e.clientY+30 + "px";
			}
		};
		ID("workspace").ondrop = e => {
			e.preventDefault();
			ID("workspace").classList.remove("uploadFileDrag");
			if (e.dataTransfer.types.includes("Files")) {
				let files = [];
				for (let i = 0; i < e.dataTransfer.files.length; i++){
					let currentFile = e.dataTransfer.files[i];
					if(currentFile.type) files.push(currentFile);
				}
				uploadFiles(files);
			}
		};
		document.onpaste = event => {
			if (!ID("files").contains(document.activeElement) && ID("files") != document.activeElement) return;
			let items = event.clipboardData.items;
			let files = [];
			for (let i = 0; i < items.length; i++) {
				if (items[i].kind == "file") files.push(items[i].getAsFile());
			}
			uploadFiles(files);
		};
		ID("tabsSwitcher").onmousedown = e => {
			ID("tabs").focus();
			if (e.button == 1) { e.preventDefault(); }
		};
		ID("tabsSwitcher").onmousewheel = e => {
			ID("tabs").focus();
			let tabEles = Array.from(document.querySelectorAll("#userTabsContainer>.tab"));
			let currentIndex = tabEles.indexOf(document.querySelector("#userTabsContainer>.tab.active"));
			if (e.deltaY < 0) {
				if (!currentIndex) tabEles[tabEles.length - 1].onpointerdown();
				else tabEles[currentIndex - 1].onpointerdown();
			} else {
				if (currentIndex == tabEles.length - 1) tabEles[0].onpointerdown();
				else tabEles[currentIndex + 1].onpointerdown();
			}
		};
		document.documentElement.ondragleave = e => { ID("workspace").classList.remove("uploadFileDrag"); };
		Array.from(document.getElementsByClassName("dialogContainer")).forEach(elem => {elem.remove();});
		if(Globals.isMobileDevice) Array.from(document.getElementsByClassName("mobileDeviceHidden")).forEach(element => {element.remove();});
		createTab("welcome");
		loadExtensions();
		execExtHook("init");
	}
	function toggleCheckbox(ele) {
		let checked = ele.classList.contains("checked");
		ele.classList[checked?"remove":"add"]("checked");
		ele.checked = !checked;
	}
	function getUpdate() {
		fetch(`${Globals.onlineServiceHost}/backend/update?version=` + Globals.currentVersion)
		.then(res => {return res.json();})
		.then(json => {
			if (!json.update.isLatest) {
				confirm(`- ${json.update.changelog.join("\n- ")}\n`, "更新至 " + json.update.latestVer + " 版本", () => {
					getLatestVersion("更新");
				});
			}
			if (json.publicNotice.text && json.publicNotice.ver != getConfig("shownPublicNotice")) {
				alert(json.publicNotice.text, "系统公告", () => { setConfig("shownPublicNotice", json.publicNotice.ver); });
			}
		})
	}
	function getLatestVersion(action) {
		let boxId = createMsgBox();
		updateMsgBox(boxId, "正在请求", "正在" + action + "，请勿操作页面 ...", "loading");
		request({action: "getLatestVersion"}).then(json => {
			switch (json.code) {
				case 200:
					hideMsgBox(boxId);
					alert(action + "完成，按「确定」刷新此页面。", action + "成功", () => { location.reload(); });
					break;
				case 1000:
					handleAuthError(boxId);
					break;
				case 1001:
					updateMsgBox(boxId, "请求失败", "无法连接至 FileAdmin 官网服务", "error", 3000, true);
					break;
				case 1002:
					updateMsgBox(boxId, "请求失败", "FileAdmin 没有权限访问其本体文件", "error", 3000, true);
					break;
				default:
					updateMsgBox(boxId, "请求失败", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
					break;
			}
		}).catch( err => {
			updateMsgBox(boxId, "网络错误", action + "失败，请重试", "error", 3000, true);
		});
	}
	function loadUiTheme(returnOnly) {
		let isDark = false;
		switch (getConfig("uiTheme")) {
			case "auto": if (matchMedia('(prefers-color-scheme: dark)').matches) isDark = true; break;
			case "dark": isDark = true; break;
		}
		if (!returnOnly) {
			if (isDark) document.documentElement.classList.add("dark");
			else document.documentElement.classList.remove("dark");
			loadEditorTheme();
			setTimeout(loadUiTheme, 1000);
		}
		return isDark;
	}
	onload = () =>{
		init();
		loadUiTheme();
		addEventListener("beforeinstallprompt", (event) => {
			event.preventDefault();
			Globals.pwaPrompt = event;
		});
	}
	
	// 消息框操作
	function createMsgBox() {
		let box = document.createElement("div");
		let boxId = new Date().getTime() + "-" + Math.round( Math.random() * 114514 );
		box.className = "msgBox hidden";
		box.id="FileAdminMsgBox-" + boxId;
		ID("msgBoxContainer").appendChild(box);
		execExtHook("msgboxcreate", box);
		return boxId;
	}
	function updateMsgBox(boxId, title, text, type, duration, userClose){
		setTimeout( ()=>{
			let box = ID("FileAdminMsgBox-" + boxId);
			if(!box) return;
			box.innerHTML = `<font class="icon"></font><div><b>${textEscape(title)}</b><div>${textEscape(text)}</div></div>`;
			if(userClose) box.innerHTML += `<font class="close" onclick="hideMsgBox('${boxId}')">&#xEB99;</div>`;
			box.className = "msgBox " + type;
			box.style.height = box.getElementsByTagName("div")[0].clientHeight + 20 + "px"; 
			if(duration) setTimeout(()=>{hideMsgBox(boxId);}, duration);
			execExtHook("msgboxupdate", box);
		}, 50);
	}
	function hideMsgBox(boxId){
		let box = ID("FileAdminMsgBox-" + boxId);
		if(!box) return;
		box.classList.add("hidden");
		execExtHook("msgboxhide", box);
		setTimeout( ()=>{ box.remove(); }, 200);
	}
	function quickMsgBox(text, boxId){
		if(!boxId) boxId = createMsgBox();
		updateMsgBox(boxId, "提示", text, null, 3000, true);
		return boxId;
	}
	
	// 用户登录
	function getLogin(silent) {
		let boxId = createMsgBox();
		if(!silent) updateMsgBox(boxId, "正在初始化", "正在读取登录信息，请稍候 ...", "loading");
		request({action: "version"})
		.then(json =>{ 
			ID("version").innerText = json.version;
			Globals.faScript = json.faScript;
			if(!json.isLogin) {
				if(!silent) updateMsgBox(boxId, "未登录", "请登录 FileAdmin 后继续", "info", 1000, true);
				ID("login").hidden = false;
				ID("loginBox").hidden = false;
				ID("totpBox").hidden = true;
				ID("workspace").hidden = true;
				document.querySelector("header").hidden = true;
				ID("loginFormUser").value = `FileAdmin（${location.host}）`;
				ID("loginFormPassword").focus();
			} else {
				if(!Globals.currentVersion) {
					Globals.currentVersion = json.version;
					getUpdate();
				}
				hideMsgBox(boxId);
				ID("workspace").hidden = false;
				ID("login").hidden = true;
				document.querySelector("header").hidden = false;
				ID("loginFormButton").disabled = false;
				getFileList("");
			}
		})
		.catch(err => {
			ID("version").innerText = "Error";
			alert("无法获取登录信息，按「确定」以重载此应用。", "网络错误", init);
		});
	}
	function submitLogin() {
		if(event) event.preventDefault();
		let boxId = createMsgBox();
		updateMsgBox(boxId, "正在登录", "正在提交登录信息，请稍候 ...", "loading");
		ID("loginFormButton").disabled = true;
		request({action: "login", password: ID("loginFormPassword").value, totp: ID("totpFormInput").value})
		.then(json =>{ 
			ID("totpFormInput").value = "";
			switch (json.code) {
				case 200: updateMsgBox(boxId, "登录成功", "欢迎使用 FileAdmin", "success", 1000, true); getLogin(true); break;
				case 1001:
					updateMsgBox(boxId, "登录失败", "密码错误，请检查后再试", "error", 3000, true);
					ID("loginBox").hidden = false;
					ID("totpBox").hidden = true;
					ID("loginFormButton").disabled = false;
					ID("loginFormPassword").focus();
					break;
				case 1002:
					alert("由于密码错误次数过多，您的请求已被拦截。\n如果您是站点管理员，请查阅官方文档以了解如何解封。", "登录失败", () => { location = Globals.onlineServiceHost + "/support"; });
					hideMsgBox(boxId);
					ID("loginFormPassword").disabled = true;
					break;
				case 1003:
					updateMsgBox(boxId, "二步验证", "请输入 TOTP 验证码后继续", "info", 3000, true);
					ID("loginBox").hidden = true;
					ID("totpBox").hidden = false;
					renderTotp();
					ID("totpFormInput").focus();
					break;
				case 1004:
					updateMsgBox(boxId, "登录失败", "验证码错误，请检查后再试", "error", 3000, true);
					renderTotp();
					ID("totpFormInput").focus();
					break;
			}
		})
		.catch(err => {
			updateMsgBox(boxId, "网络错误", "无法提交登录信息", "error", 3000, true);
			ID("loginFormButton").disabled = false;
			ID("loginFormPassword").focus();
		});
	}
	function logout() {
		confirm("确实要退出登录吗？您将丢失当前的工作区。", "确认", () => {
			let boxId = createMsgBox();
			updateMsgBox(boxId, "正在注销", "正在退出登录，请稍候 ...", "loading");
			request({action: "logout"})
			.then(json =>{ 
				updateMsgBox(boxId, "注销成功", "您已成功退出登录", "success", 1000, true);
				ID("loginFormPassword").value = "";
				init(true);
			})
			.catch(err => {
				updateMsgBox(boxId, "网络错误", "注销失败，请稍候再试", "error", 3000, true);
			});
		});
	}

	// TOTP相关函数
	function renderTotp() {
		let input = ID("totpFormInput");
		let value = input.value.trim().slice(0, 6);
		input.value = value;
		let valueSplitted = value.split("");
		document.querySelectorAll("#totpForm>div").forEach(ele => ele.innerText = "");
		valueSplitted.forEach((num, index) => {
			document.querySelector(`#totpForm>div:nth-child(${index + 1})`).innerText = num;
		});
		if (value.length == 6) submitLogin();
	}
	function getTotpStatus(tabId = Globals.currentTab) {
		document.querySelectorAll(`#FileAdminTabContent-${tabId} button`).forEach(ele => ele.disabled = true);
		request({action: "totpStatus"})
		.then(json => {
			document.querySelectorAll(`#FileAdminTabContent-${tabId} button`).forEach(ele => ele.disabled = false);
			getTabContent(tabId, ".totpStatus").innerText = "获取失败";
			switch (json.code) {
				case 200: 
					if (json.enabled) {
						getTabContent(tabId, ".totpStatus").innerText = "已开启";
						getTabContent(tabId, ".totpOnBtn").hidden = true;
						getTabContent(tabId, ".totpOffBtn").hidden = false;
					} else {
						getTabContent(tabId, ".totpStatus").innerText = "已关闭";
						getTabContent(tabId, ".totpOnBtn").hidden = false;
						getTabContent(tabId, ".totpOffBtn").hidden = true;
					}
				break;
				case 1000: handleAuthError(); break;
			}
		});
	}
	function enableTotp(tabId = Globals.currentTab) {
		document.querySelectorAll(`#FileAdminTabContent-${tabId} button`).forEach(ele => ele.disabled = true);
		request({action: "totpEnable"})
		.then(json => {
			document.querySelectorAll(`#FileAdminTabContent-${tabId} button`).forEach(ele => ele.disabled = false);
			switch (json.code) {
				case 200: 
					getTabContent(tabId, ".totpConfirmInput").value = "";
					getTabContent(tabId, ".totpStatusBox").hidden = true;
					getTabContent(tabId, ".totpConfigurationBox").hidden = false;
					let qrContainer = getTabContent(tabId, ".totpQrcode");
					qrContainer.innerHTML = "<div><font>&#xF03F;</font> 二维码加载中</div>";
					loadRemoteScript(getStaticUrl("qrcode"), () => {
						qrContainer.innerHTML = "";
						new QRCode(qrContainer, {
							text: `otpauth://totp/${encodeURIComponent(location.host)}?secret=${json.secret}&issuer=FileAdmin`,
							width: 260,
							height: 260,
							colorDark : loadUiTheme(true)?"#F0F0F0":"#000000",
							colorLight : loadUiTheme(true)?"#1E1E1E":"#F9F9FB",
							correctLevel : QRCode.CorrectLevel.H,
						});
						qrContainer.title = "";
					});
				break;
				case 1000: handleAuthError(); break;
			}
		});
	}
	function confirmTotp(tabId = Globals.currentTab) {
		document.querySelectorAll(`#FileAdminTabContent-${tabId} button`).forEach(ele => ele.disabled = true);
		let boxId = createMsgBox();
		updateMsgBox(boxId, "正在验证", "正在验证 TOTP 配置，请稍候 ...", "loading"); 
		request({action: "totpConfirm", totp: getTabContent(tabId, ".totpConfirmInput").value})
		.then(json => {
			document.querySelectorAll(`#FileAdminTabContent-${tabId} button`).forEach(ele => ele.disabled = false);
			switch (json.code) {
				case 200:
					updateMsgBox(boxId, "配置成功", "二步验证已开启，下次登录此副本时将要求您输入验证码", "success", 3000, true);
					getTabContent(tabId, ".totpStatusBox").hidden = false;
					getTabContent(tabId, ".totpConfigurationBox").hidden = true;
					getTotpStatus(tabId);
				break;
				case 1000: handleAuthError(boxId); break;
				case 1001: updateMsgBox(boxId, "配置失败", "您填写的验证码有误", "error", 3000, true); break;
			}
		});
	}
	function disableTotp(tabId = Globals.currentTab) {
		confirm("确实要关闭二步验证吗？", "关闭二步验证", () => {
			document.querySelectorAll(`#FileAdminTabContent-${tabId} button`).forEach(ele => ele.disabled = true);
			let boxId = createMsgBox();
			updateMsgBox(boxId, "正在关闭", "正在关闭二步验证，请稍候 ...", "loading");
			request({action: "del", files: ["/.FileAdmin/totp-data"]}).then( json => {
				getTotpStatus(tabId);
				switch (json.code) {
					case 200: updateMsgBox(boxId, "关闭成功", "已成功关闭二步验证", "success", 3000, true); break;
					case 1000: handleAuthError(boxId); break;
					case 1001: updateMsgBox(boxId, "关闭失败", "请检查数据目录访问权限配置", "error", 3000, true); break;
					default: updateMsgBox(boxId, "未知错误", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true); break;
				}
			}).catch( err => { updateMsgBox(boxId, "关闭失败", "网络错误，请检查网络连接", "error", 3000, true); });
		}, true);
	}

	
	// 工作区保存
	function saveLocalWorkspace() {
		for (let tabId in Globals.tabs) {
			if (Globals.tabs[tabId] == "welcome") getTabContent(tabId, ".restoreBtn").hidden = true;
		}
		clearTimeout(Globals.workspaceSaveTimeout);
		Globals.workspaceSaveTimeout = setTimeout(() => {
			let workspace = {
				time: new Date().getTime(),
				editors: [],
				dir: Globals.currentDir,
			};
			for (let tabId in Globals.editors) {
				workspace.editors.push({
					file: Globals.editors[tabId].file,
					content: editorData[getConfig("textEditor")].getValue(tabId),
					lastSaveContent: Globals.editors[tabId].lastSaveContent,
					saved: editorData[getConfig("textEditor")].getValue(tabId) == Globals.editors[tabId].lastSaveContent,
				});
			}
			if (!workspace.editors.length && !Globals.currentDir) setConfig("lastSaveWorkspace", null);
			else setConfig("lastSaveWorkspace", workspace);
			execExtHook("workspacesave", workspace);
		}, 5000);
	}
	function restoreWorkspace() {
		if (!getConfig("lastSaveWorkspace")) return;
		if (!editorData[getConfig("textEditor")].check()) return quickMsgBox("请等待资源文件加载，若此提示频繁出现请在设置中修改「静态资源提供方」");
		let lastSaveWorkspace = getConfig("lastSaveWorkspace");
		confirm(`是否恢复您 ${humanTime(lastSaveWorkspace.time)} 在此设备打开的目录和编辑器？\n如果您此后在其他设备编辑了文件，修改将不会自动同步。`, "恢复工作", () => {
			if (getTabContent(Globals.currentTab, ".restoreBtn")) getTabContent(Globals.currentTab, ".restoreBtn").hidden = true;
			getFileList(lastSaveWorkspace.dir);
			lastSaveWorkspace.editors.forEach(data => {
				let tabId = createTab("editor", {file: data.file, content: data.content});
				if (!data.saved) {
					updateTab(tabId, {closeIcon: "&#xEB96;"});
					Globals.editors[tabId].lastSaveContent = data.lastSaveContent;
				}
				updateFileTab(tabId, data.file);
			});
			Globals.requestedWorkspaceRestore = true;
		});
	}
	
	// 配置系统
	let configData = {
		uiTheme: {
			uiName: "界面主题",
			type: "select",
			selections: { 
				"light": "浅色模式", 
				"dark": "深色模式", 
				"auto": "跟随系统", 
			},
			defaultValue: "auto",
			validFunction() {
				setTimeout(loadUiTheme, 10);
				return true;
			},
		},
		editorTheme: {
			uiName: "文本编辑器主题",
			description: "当使用「原生文本框」作为文本编辑器时，此功能将不会生效。",
			type: "select",
			selections: { 
				"light": "浅色模式", 
				"dark": "深色模式", 
				"auto": "跟随界面主题", 
			},
			defaultValue: "auto",
			validFunction() {
				setTimeout(loadEditorTheme, 10);
				return true;
			},
		},
		staticHost: {
			uiName: "静态资源提供方",
			description: "如果您的 FileAdmin 出现资源加载缓慢、文本编辑器初始化失败、图标无法显示等问题，请尝试切换此设置；<b>自有加速源由 <a href='https://starxn.com/aff/LLBHWTEJ' target='_blank'>星辰云 <font>&#xF0F4;</font></a> 赞助托管。</b>您需要刷新此应用以使设置生效。",
			type: "select",
			selections: Object.keys(staticHosts).reduce((result, current) => { result[current] = current; return result; }, {}),
			defaultValue: Object.keys(staticHosts)[0],
			validFunction() {
				quickMsgBox("配置成功，您需要刷新页面以使此设置生效");
				return true;
			},
		},
		textEditor: {
			uiName: "文本编辑器选择",
			description: "<b>修改此配置后，应用将立刻重载，请提前保存您的工作</b>；在移动设备推荐使用「ACE Editor」进行编辑。",
			type: "select",
			selections: { 
				"monaco": "Monaco Editor（VSCode）", 
				"ace": "ACE Editor", 
				"textarea": "原生文本框（Textarea）", 
			},
			defaultValue: "monaco",
			validFunction() {
				setTimeout(() => {
					init();
					createTab("settings");
				}, 10);
				return true;
			},
		},
		editorFontSize: {
			uiName: "文本编辑器字号",
			description: "设置文本文件编辑器字体大小，推荐 10 - 20 之间，单位：像素。",
			type: "number",
			defaultValue: "12",
			validFunction(value) {
				if (value > 30 || value < 8) {
					quickMsgBox("您设置的字号过小或过大");
					return false;
				}
				setTimeout(editorData[getConfig("textEditor")].updateConfig, 10);
				return true;
			},
		},
		editorLineHeight: {
			uiName: "文本编辑器行高",
			description: "设置文本文件编辑器行高，推荐 12 - 25 之间，单位：像素。",
			type: "number",
			defaultValue: "14",
			validFunction(value) {
				if (value > 50 || value < 8) {
					quickMsgBox("您设置的行高过小或过大");
					return false;
				}
				setTimeout(editorData[getConfig("textEditor")].updateConfig, 10);
				return true;
			},
		},
		editorIndentType: {
			uiName: "文本编辑器缩进类型",
			type: "select",
			selections: { 
				"tab": "使用制表符（Tab）", 
				"space": "使用空格代替制表符", 
			},
			defaultValue: "tab",
			validFunction() {
				setTimeout(editorData[getConfig("textEditor")].updateConfig, 10);
				return true;
			},
		},
		editorIndentSize: {
			uiName: "文本编辑器缩进大小",
			type: "select",
			selections: { 
				"2": "2 字符", 
				"4": "4 字符", 
				"8": "8 字符", 
			},
			defaultValue: "4",
			validFunction() {
				setTimeout(editorData[getConfig("textEditor")].updateConfig, 10);
				return true;
			},
		},
		editorWrap: {
			uiName: "文本编辑器自动折行",
			type: "select",
			selections: { 
				"off": "默认关闭", 
				"on": "默认开启", 
			},
			defaultValue: "off",
			validFunction() {
				setTimeout(editorData[getConfig("textEditor")].updateConfig, 10);
				return true;
			},
		},
		uploadTrunkSize: {
			uiName: "文件分片大小",
			description: "上传文件时每个文件切片的大小，推荐 2 - 10 之间，单位 MB。",
			type: "number",
			defaultValue: "2",
			validFunction(value) {
				if (value > 15 || value < 1 || Math.round(value) != value) {
					quickMsgBox("单个分片大小请设置为 1 - 15 MB 之间的整数");
					return false;
				}
				if (Object.keys(Globals.upload).length) {
					updateMsgBox(createMsgBox(), "任务进行中", "请等待进行中的上传任务完成后继续操作", "error", 3000, true);
					return false;
				}
				return true;
			},
		},
		mathVerify: {
			uiName: "四则运算验证",
			description: "进行部分操作时，您可能需要输入四则运算结果以进行确认。若您认为此功能毫无必要，可以在此处关闭。",
			type: "select",
			selections: { 
				"on": "开启", 
				"off": "关闭", 
			},
			defaultValue: "on",
		},
		showHiddenFiles: {
			uiName: "显示隐藏文件",
			description: "在文件管理器中显示 FileAdmin 运行所需的文件；编辑此类文件可能造成 FileAdmin 无法运行甚至丢失重要数据，请慎重。",
			type: "select",
			selections: { 
				"off": "关闭（推荐）", 
				"on": "开启（仅作调试用途）", 
			},
			defaultValue: "off",
			validFunction() {
				setTimeout(getFileList, 10);
				return true;
			},
		},
		extSideload: {
			uiName: "扩展侧载",
			description: "在打开 FileAdmin 时从此处填写的 JSON 配置信息加载扩展，详见 <a href='" + Globals.onlineServiceHost + "/dev' target='_blank'>扩展开发文档 <font>&#xF0F4;</font></a>。",
			type: "text",
			placeholder: "请输入扩展配置信息 ...",
			defaultValue: "",
			validFunction() {
				setTimeout(() => {
					try {
						let config = JSON.parse(getConfig("extSideload"));
						if (!config.url || !config.requirements) throw("");
						else alert("扩展侧载配置成功，将在刷新此应用后生效。\n请确保此配置中的扩展资源文件 URL 由您完全控制，永远不要将他人向您提供的任何内容填入此处。");
					}
					catch (err) {
						if (getConfig("extSideload")) alert("您填写的扩展侧载配置信息有误。\n这不会影响您正常使用 FileAdmin，但若您需要侧载扩展，则应对照开发文档检查语法是否正确。");
					}
				}, 10);
				return true;
			},
		},
		installLink: {
			uiName: "安装快捷方式",
			description: "在系统中安装桌面快捷方式以使用更多快捷键，例如按 Ctrl+W 关闭标签页。",
			type: "button",
			text: "在系统中安装快捷方式",
			clickFunction() {
				if (Globals.pwaPrompt) {
					Globals.pwaPrompt.prompt();
					Globals.pwaPrompt.userChoice.then((choiceResult) => {
						if (choiceResult.outcome == "accepted") {
							alert(`FileAdmin 快捷方式安装成功。`);
						} else {
							updateMsgBox(createMsgBox(), "安装失败", "您的浏览器拒绝了安装请求", "error", 3000, true);
						}
					});
				} else {
					updateMsgBox(createMsgBox(), "安装失败", "此功能当前不可用。目前仅支持国际主流 PC 浏览器，且需要使用 HTTPS 协议访问此应用", "info", 3000, true);
				}
			},
		},
		changePassword: {
			uiName: "修改密码",
			type: "button",
			description: "修改此 FileAdmin 副本的登录密码；推荐定期设置新密码以确保安全。",
			text: "修改 FileAdmin 登录密码",
			clickFunction() {
				prompt("请输入新密码 ...", "修改密码", "", password => {
					let boxId = createMsgBox();
					updateMsgBox(boxId, "正在请求", "正在修改密码，请稍候 ...", "loading");
					request({ action: "changePassword", password: password }).then(json => {
						switch (json.code) {
							case 200: updateMsgBox(boxId, "请求成功", "当前副本的密码更新成功，您可能需要在其他设备上重新登录", "success", 3000, true); break;
							case 1000: handleAuthError(boxId); break;
							case 1001: updateMsgBox(boxId, "请求失败", "FileAdmin 没有权限访问其本体文件", "error", 3000, true); break;
							default: updateMsgBox(boxId, "请求失败", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true); break;
						}
					}).catch( err => {
						updateMsgBox(boxId, "网络错误", "密码更新失败，请重试", "error", 3000, true);
					});
				}, "为了安全起见，请勿设置过于简单或常见的密码。", "password");
			},
		},
		totpConfig: {
			uiName: "二步验证",
			type: "button",
			description: "为此 FileAdmin 副本配置 TOTP 验证码以提高其安全性。",
			text: "配置 TOTP 二步验证",
			clickFunction() {
				createTab("totp");
			},
		},
		delFailedLogin: {
			uiName: "删除登录失败记录",
			type: "button",
			description: "清除当前副本的所有登录失败记录，以释放因登录失败次数过多而被拉黑的 IP 地址。",
			text: "删除所有登录失败记录",
			clickFunction() {
				let boxId = createMsgBox();
				updateMsgBox(boxId, "正在删除", "正在删除记录，请稍候 ...", "loading");
				request({action: "del", files: ["/.FileAdmin/failed-login"]}).then( json => {
					switch (json.code) {
						case 200: updateMsgBox(boxId, "删除成功", "已成功删除登录失败记录", "success", 3000, true); break;
						case 1000: handleAuthError(boxId); break;
						case 1001: updateMsgBox(boxId, "删除失败", "请检查数据目录访问权限配置", "error", 3000, true); break;
						default: updateMsgBox(boxId, "未知错误", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true); break;
					}
				}).catch( err => { updateMsgBox(boxId, "删除失败", "网络错误，请检查网络连接", "error", 3000, true); });
			},
		},
		repairVersion: {
			uiName: "联网修复",
			type: "button",
			description: "从 FileAdmin 官网获取最新版本；当您误操作修改了当前副本的代码时可使用此功能恢复到官方版本。",
			text: "联网修复 FileAdmin 副本",
			clickFunction() {
				confirm("将从 FileAdmin 官网获取最新版本，是否继续？", "联网修复", password => {
					getLatestVersion("修复");
				}, "true");
			},
		},
		configSync: {
			uiName: "配置同步",
			description: "在不同浏览器或设备中享受一致的 FileAdmin 体验。",
			type: "button",
			text: "导出或导入 FileAdmin 配置信息",
			clickFunction() {
				if (!localStorage.FileAdmin_Config) localStorage.FileAdmin_Config = "{}";
				let currentConfigBase64 = base64(localStorage.FileAdmin_Config);
				prompt("在此粘贴配置内容，按「确定」以导入 ...", "配置同步", currentConfigBase64, text => {
					if (text.trim() != currentConfigBase64) {
						try {
							let newConfigJson = atob(text.trim());
							JSON.parse(newConfigJson);
							confirm("确实要导入此配置吗？您当前的配置将被永久覆盖。\n请不要跨版本导入配置信息，或导入来路不明的配置信息。\n按下「确定」后，应用将立刻重载，请提前保存您的工作。", "配置同步", () => {
								localStorage.FileAdmin_Config = newConfigJson;
								init();
								alert("您的配置已成功导入。", "配置同步");
								createTab("settings");
							});
						} catch(err) {
							updateMsgBox(createMsgBox(), "导入失败", "此配置信息不完整或已被损坏，请从其他设备重新导出使用", "error", 3000, true);
						}
					}
				}, "粘贴到其他设备以导出配置，或覆盖以下信息以导入配置");
			},
		},
		filePanelWidth: {defaultValue: 300,},
		shownPublicNotice: {},
		fileModifier: {defaultValue: {},},
		lastSaveWorkspace: {defaultValue: null,},
		loadedExtensions: {defaultValue: [],},
		defaultFileHandler: {defaultValue: {},},
	}
	function setConfig(key, value, showMsgBox) {
		let config = localStorage.FileAdmin_Config;
		if (!config) config = "{}"; 
		try {
			let configJson = JSON.parse(config);
			if (!configData[key]) return updateMsgBox(createMsgBox(), "出现错误", "更新配置时出现错误，您可以向我们反馈此问题", "error", 3000, true);
			if (!configData[key].validFunction || configData[key].validFunction(value)) {
				configJson[key] = value;
				localStorage.FileAdmin_Config = JSON.stringify(configJson);
				if (showMsgBox) updateMsgBox(createMsgBox(), "配置成功", "FileAdmin 配置更新成功", "success", 3000, true);
			}
			renderConfigTab();
			execExtHook("configupdate", key, value);
		} catch(err) {
			alert("FileAdmin 系统配置损坏，无法正常运行。\n清除数据有助于解决此问题，这将不会影响主机内的任何文件。\n按「确定」以清除所有本地数据。", "严重错误", () => {
				localStorage.FileAdmin_Config = "{}";
				alert("配置数据已成功清除。\n若按「确定」后仍出现错误，请向我们反馈。", "提示", init);
			});
		}
	}
	function getConfig(key) {
		let config = localStorage.FileAdmin_Config;
		if (!config) config = "{}";
		try {
			let configJson = JSON.parse(config);
			if (!configJson[key]) return configData[key].defaultValue;
			return configJson[key];
		} catch(err) {
			alert("FileAdmin 系统配置损坏，无法正常运行。\n清除数据有助于解决此问题，这将不会影响主机内的任何文件。\n按「确定」以清除所有本地数据。", "严重错误", () => {
				localStorage.FileAdmin_Config = "{}";
				alert("配置数据已成功清除。\n若按「确定」后仍出现错误，请向我们反馈。", "提示", init);
			});
		}
	}
	function getStaticUrl(lib) {
		let currentHost = getConfig("staticHost");
		if (!staticHosts[currentHost]) {
			setConfig("staticHost", Object.keys(staticHosts)[0]);
			return getStaticUrl(lib);
		}
		return staticHosts[currentHost][lib];
	}
	new FontFace("icon", "url("+getStaticUrl("remixicon")+")").load()
	.then(function(loadedFont) { document.fonts.add(loadedFont); });
	function renderConfigTab() {
		if (!Globals.tabs[Globals.configTabId]) return;
		let settingsContainer = getTabContent(Globals.configTabId, ".settingsContainer");
		settingsContainer.innerHTML = `<div class="title"><font>&#xF0EE;</font> 配置选项</div>`;
		for (let configKey in configData) {
			let currentConfigData = configData[configKey];
			if (!currentConfigData.uiName) continue;
			let configElement = document.createElement("div");
			configElement.classList.add("config");
			configElement.innerHTML = `<div class="title">${currentConfigData.uiName}</div>`;
			if (currentConfigData.description) configElement.innerHTML += `<div class="description">${currentConfigData.description}</div>`;
			switch (currentConfigData.type) {
				case "select":
					let selectElement = document.createElement("select");
					for (selectionName in currentConfigData.selections) selectElement.innerHTML += `<option value="${selectionName}">${currentConfigData.selections[selectionName]}</option>`;
					selectElement.value = getConfig(configKey);
					selectElement.onchange = () => { setConfig(configKey, selectElement.value, true) }
					configElement.appendChild(selectElement);
					break;
				case "number":
					let inputElement = document.createElement("input");
					inputElement.type = "number";
					inputElement.value = getConfig(configKey);
					inputElement.onblur = () => { setConfig(configKey, inputElement.value, true) }
					configElement.appendChild(inputElement);
					break;
				case "text":
					let textElement = document.createElement("textarea");
					textElement.value = getConfig(configKey);
					textElement.placeholder = currentConfigData.placeholder || "请输入内容 ...";
					textElement.onblur = () => { setConfig(configKey, textElement.value, true) }
					configElement.appendChild(textElement);
					break;
				case "button":
					let buttonElement = document.createElement("button");
					buttonElement.innerText = currentConfigData.text;
					buttonElement.onclick = currentConfigData.clickFunction;
					buttonElement.classList.add("sub");
					configElement.appendChild(buttonElement);
					break;
			}
			settingsContainer.appendChild(configElement);
		}
		let footer = document.createElement("div");
		footer.classList.add("footer");
		footer.innerHTML = `<a href="https://www.nlrdev.top/chat" target="_blank"><font>&#xF03B;</font> 用户反馈社群</a>`;
		settingsContainer.appendChild(footer);
		getTabContent(Globals.configTabId).focus();
	}
	
	// 扩展系统
	function loadExtensions() {
		fetch(Globals.onlineServiceHost + "/backend/extension/extensions.json?stamp=" + new Date().getTime())
		.then(res => { return res.json(); })
		.then(json => {
			Globals.extRuntimeInfomation = json;
			let loadedExtensions = getConfig("loadedExtensions");
			let sideloadConfig;
			try {
				sideloadConfig = JSON.parse(getConfig("extSideload"));
				if (sideloadConfig.url && sideloadConfig.requirements) {
					Globals.extRuntimeInfomation.sideload = sideloadConfig;
					loadedExtensions.push("sideload");
				}
			} catch (err) {}
			for (let extName in json) {
				if (loadedExtensions.includes(extName) && !Globals.extLoadedState.includes(extName)) {
					let filesToLoad = json[extName].requirements;
					filesToLoad.push(extName != "sideload" ? Globals.onlineServiceHost + "/backend/extension/extensions/scripts/" + extName + ".js?v=" + json[extName].version : Globals.extRuntimeInfomation.sideload.url);
					if (json[extName].blockEditor) Globals.extRuntime.blockEditorLoad++;
					if (json[extName].minRuntime > Globals.extRuntime.version) quickMsgBox(`扩展「${json[extName].uiName??"侧载扩展"}」需要更高的运行环境版本，请更新 FileAdmin 以继续使用此扩展`);
					else loadExtension(extName, filesToLoad);
				}
			}
			initTextEditor();
		})
		.catch(err => {
			console.warn(err);
			initTextEditor();
			updateMsgBox(createMsgBox(), "出现错误", "扩展运行环境初始化失败，扩展相关功能将无法正常工作", "error", null, true);
		});
	}
	function loadExtension(name, filesToLoad, loadedFile = 0) {
		if (filesToLoad[loadedFile]) {
			loadRemoteScript(filesToLoad[loadedFile], () => {
				loadExtension(name, filesToLoad, loadedFile + 1);
			}, () => {
				updateMsgBox(createMsgBox(), "出现错误", `扩展「${Globals.extRuntimeInfomation[name].uiName??"侧载扩展"}」初始化失败，相关功能将无法正常工作`, "error", null, true);
			});
		} else {
			if (Globals.extRuntimeInfomation[name].blockEditor) initTextEditor();
			Globals.extLoadedState.push(name);
		}
	}
	window.addEventListener("message", event => {
		let msg = event.data;
		if (!msg.installExt) return;
		if (Globals.extRuntimeInfomation[msg.installExt]) {
			let extInfo = Globals.extRuntimeInfomation[msg.installExt];
			let currentExtensions = getConfig("loadedExtensions");
			if (currentExtensions.includes(msg.installExt)) {
				confirm(`确实要删除扩展「${extInfo.uiName}」吗？`, "删除扩展", () => {
					currentExtensions = currentExtensions.filter(item => item != msg.installExt);
					setConfig("loadedExtensions", currentExtensions);
					alert("扩展已成功删除，您需要刷新此应用以卸载此扩展。");
					getTabContent(Globals.currentTab, "iframe").contentWindow.postMessage({fileadminLoadedExt: getConfig("loadedExtensions")}, "*");
				});
			} else {
				confirm(`安装来源：${textEscape(new URL(event.origin).hostname)}\n扩展名称：${extInfo.uiName}\n${extInfo.developer=="@official"?"":"此扩展并非由官方开发，请确保您信任其提供者。\n"}如果这不是您的本人操作，请不要进行安装。`, "安装扩展", () => {
					currentExtensions.push(msg.installExt);
					setConfig("loadedExtensions", currentExtensions);
					loadExtensions();
					getTabContent(Globals.currentTab, "iframe").contentWindow.postMessage({fileadminLoadedExt: getConfig("loadedExtensions")}, "*");
				});
			}
		} else {
			updateMsgBox(createMsgBox(),"出现错误",  "您请求安装的扩展暂未上架", "error", 3000, true);
		}
	});
	function addExtHook(hook, handleFunction) {
		if (!Globals.extRuntime.extHooks[hook]) Globals.extRuntime.extHooks[hook] = [];
		Globals.extRuntime.extHooks[hook].push(handleFunction);
	}
	function execExtHook(hook, ...rest) {
		if (!Globals.extRuntime.extHooks[hook]) return;
		let availableHooks = Globals.extRuntime.extHooks[hook];
		availableHooks.forEach(func => {
			try { func(...rest); }
			catch (err) { console.warn(err); }
		});
	}
	
	// 文件栏调整
	ID("resizer").addEventListener("mousedown", startResize);
	function startResize(e) {
		document.addEventListener("mousemove", resize);
		document.addEventListener("mouseup", stopResize);
		document.documentElement.style.cursor = "col-resize";
		ID("tabs").style.transition = "none";
	}
	function resize(e) {
		let x = e.pageX;
		let distance = Math.max(212, Math.min(400, x));
		document.documentElement.style.setProperty("--FilesWidth", distance + "px");
		setConfig("filePanelWidth", distance);
		editorData[getConfig("textEditor")].resize();
	}
	function stopResize() {
		document.removeEventListener("mousemove", resize);
		document.removeEventListener("mouseup", stopResize);
		document.documentElement.style.cursor = "";
		ID("tabs").style.transition = "";
	}
	
	// 对话框
	function createDialogElement(html) {
		let dialogId = new Date().getTime() + "-" + Math.round( Math.random() * 114514 );
		Globals.dialogActiveElement[dialogId] = document.activeElement;
		let dialogDiv = document.createElement("div");
		dialogDiv.classList.add("dialogContainer");
		dialogDiv.id = "FileAdminDialogContainer-" + dialogId;
		dialogDiv.tabIndex = 0;
		dialogDiv.innerHTML = `<div class="dialog">${html.replaceAll("{{dialogId}}", dialogId)}</div>`;
		return {element: dialogDiv, id: dialogId};
	}
	function alert(content, title = "提示", onsubmit, isHtml) {
		let dialog = createDialogElement(`
			<div class="title">${textEscape(title)}</div>
			<div class="content">${isHtml?content:textEscape(content)}</div>
			<div class="buttons"><button onclick="Globals.dialogCallbacks['{{dialogId}}']()"><font>&#xEB81;</font> 确定</button></div>
		`);
		Globals.dialogCallbacks[dialog.id] = () => {
			closeDialog(dialog.id);
			if (onsubmit) onsubmit();
		};
		dialog.element.onkeyup = () => { if (event.key == " " || event.key == "Enter" || event.key.toLowerCase() == "y" || event.key == "Escape") Globals.dialogCallbacks[dialog.id](); }
		document.body.appendChild(dialog.element);
		dialog.element.focus();
		execExtHook("dialogcreate", "alert", dialog.element);
		return dialog.id;
	}
	function confirm(content, title, onsubmit, mathConfirm) {
		if (mathConfirm && getConfig("mathVerify") != "off") {
			let number1 = Math.floor(Math.random() * 5 + 1);
			let number2 = Math.floor(Math.random() * 5 + 1);
			let operator = ['+', '-', '×'][Math.floor(Math.random() * 3)];
			let result;
			switch (operator) {
				case '+': result = number1 + number2; break;
				case '-': result = number1 - number2; break;
				case '×': result = number1 * number2; break;
			}
			prompt("请输入计算结果 ...", title, null, (input) => {
				if (input == result) { if (onsubmit) onsubmit(); }
				else updateMsgBox(createMsgBox(), "计算错误", "计算结果有误，已取消操作", "info", 2000, true)
			}, `${content}\n请输入算式 ${number1 + operator + number2} 的计算结果以确认操作。`, "number");
		} else {
			let dialog = createDialogElement(`
				<div class="title">${textEscape(title)}</div>
				<div class="content">${textEscape(content)}</div>
				<div class="buttons">
					<button onclick="closeDialog('{{dialogId}}')" class="sub">取消</button>
					<button onclick="Globals.dialogCallbacks['{{dialogId}}']()"><font>&#xEB81;</font> 确定</button>
				</div>
			`);
			Globals.dialogCallbacks[dialog.id] = () => {
				closeDialog(dialog.id);
				if (onsubmit) onsubmit();
			};
			dialog.element.onkeyup = () => {
				if (event.key == " " || event.key == "Enter" || event.key.toLowerCase() == "y") Globals.dialogCallbacks[dialog.id]();
				else if (event.key.toLowerCase() == "n" || event.key == "Escape") closeDialog(dialog.id);
			}
			document.body.appendChild(dialog.element);
			dialog.element.focus();
			execExtHook("dialogcreate", "confirm", dialog.element);
			return dialog.id;
		}
	}
	function prompt(placeholder, title, defaultValue, onsubmit, additionalText = "", type = "text") {
		let dialog = createDialogElement(`
			<div class="title">${textEscape(title)}</div>
			<div class="content">
				${textEscape(additionalText)}
				<input id="FileAdminDialogInput-{{dialogId}}" type="${type}">
			</div>
			<div class="buttons">
				<button onclick="closeDialog('{{dialogId}}')" class="sub">取消</button>
				<button onclick="Globals.dialogCallbacks['{{dialogId}}']()"><font>&#xEB81;</font> 确定</button>
			</div>
		`);
		Globals.dialogCallbacks[dialog.id] = () => {
			if (onsubmit) onsubmit(ID("FileAdminDialogInput-" + dialog.id).value);
			closeDialog(dialog.id);
		};
		dialog.element.onkeyup = () => {
			if (event.key == "Enter") Globals.dialogCallbacks[dialog.id]();
			else if (event.key == "Escape") closeDialog(dialog.id);
		}
		document.body.appendChild(dialog.element);
		let inputElement = dialog.element.getElementsByTagName("input")[0];
		if (placeholder) inputElement.placeholder = placeholder;
		if (defaultValue) inputElement.value = defaultValue;
		inputElement.select();
		setTimeout(() => { inputElement.select(); }, 1);
		execExtHook("dialogcreate", "confirm", dialog.element);
		return dialog.id;
	}
	function pickDir(title, defaultDir, onsubmit) {
		let dialog = createDialogElement(`
			<div class="title">${textEscape(title)}</div>
			<div class="content dirPickerContent">
				<div class="fileAddressBar"><div class="address"></div></div>
				<div class="fileList"><center><font></font>正在获取文件列表</center></div>
				<div class="fileLoadingLayer"></div>
			</div>
			<div class="buttons">
				<button onclick="closeDialog('{{dialogId}}')" class="sub icoOnly"><font>&#xEB97;</font></button>
				<button class="sub icoOnly newFolderBtn"><font>&#xED5A;</font></button>
				<button onclick="Globals.dialogCallbacks['{{dialogId}}']()" disabled class="dirSubmitBtn"><font>&#xED70;</font> 选择此目录</button>
			</div>
		`);
		Globals.dialogCallbacks[dialog.id] = () => {
			closeDialog(dialog.id);
			if (onsubmit) onsubmit(Globals.dirPickerResults[dialog.id]);
		};
		dialog.element.onkeyup = () => {
			if (event.key == "Escape") closeDialog(dialog.id);
			if (event.key == "Enter") dialog.element.getElementsByClassName("dirSubmitBtn")[0].click();
		}
		if (!defaultDir) defaultDir = "";
		document.body.appendChild(dialog.element);
		renderFileListInPicker(defaultDir, dialog.id);
		dialog.element.focus();
		dialog.element.getElementsByClassName("fileAddressBar")[0].onclick = () => { editPath(dir => {renderFileListInPicker(dir, dialog.id)}); }
		dialog.element.getElementsByClassName("newFolderBtn")[0].onclick = () => { newFile("folder", Globals.dirPickerResults[dialog.id], newDir => { renderFileListInPicker(newDir, dialog.id); }) }
		execExtHook("dialogcreate", "picker", dialog.element);
		return dialog.id;
	}
	function renderFileListInPicker(dir, dialogId) {
		dir = formatDir(dir);
		Globals.dirPickerResults[dialogId] = dir;
		let dialogElement = ID("FileAdminDialogContainer-" + dialogId);
		dialogElement.getElementsByClassName("dirSubmitBtn")[0].disabled = true;
		dialogElement.getElementsByClassName("fileLoadingLayer")[0].hidden = false;
		request({ action: "dir", dir: dir }).then( json => {
			dialogElement.getElementsByClassName("fileLoadingLayer")[0].hidden = true;
			renderFileAddress(dialogElement.getElementsByClassName("address")[0], dir, dir => { event.stopPropagation();renderFileListInPicker(dir, dialogId); });
			switch (json.code) {
				case 200:
					json.files = json.files.filter(file => {return file.isDir;});
					dialogElement.getElementsByClassName("dirSubmitBtn")[0].disabled = false;
					renderFileList(json.files, dialogElement.getElementsByClassName("fileList")[0], (name, isDir, fileDiv) => {
						if (isDir) renderFileListInPicker(name, dialogId);
					});
					break;
				case 1000:
					handleAuthError();
					break;
				case 1001:
					updateMsgBox(createMsgBox(), "目录读取失败", "您尝试打开的目录已被删除", "error", 3000, true);
					renderFileListInPicker("", dialogId);
					break;
				default:
					updateMsgBox(createMsgBox(), "目录读取失败", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
					break;
			}
	 	}).catch(err => {
			renderFileAddress(dialogElement.getElementsByClassName("address")[0], dir, dir => { event.stopPropagation();renderFileListInPicker(dir, dialogId); });
			dialogElement.getElementsByClassName("fileList")[0].innerHTML = "<center><font>&#xED7F;</font>网络错误，请检查网络连接</center>";
		});
	}
	function closeDialog(dialogId) {
		let dialogDiv = ID("FileAdminDialogContainer-" + dialogId);
		if (dialogDiv) dialogDiv.remove();
		if (Globals.dialogActiveElement[dialogId]) Globals.dialogActiveElement[dialogId].focus();
	}

	// 标签页操作
	let tabData = {
		welcome: {
			defaultTitle: "欢迎",
			defaultIcon: "&#xEE21;",
			singleInstance: true,
			initHtml: `<div class="welcome" ondragstart="event.preventDefault()">
				<div class="greeting">
					<img src="https://static.nlrdev.top/sites/fa-inf/icon.png">
					<div><b>欢迎使用</b><div class="greetingText"></div></div>
				</div>
				<div class="welcomeLinks">
					<a onclick="restoreWorkspace()" class="restoreBtn" hidden><font>&#xECA5;</font> 恢复工作</a>
					<a href="${Globals.onlineServiceHost}/support" target="_blank"><font>&#xEA7E;</font> 快速上手</a>
					<a onclick="createTab('settings')"><font>&#xF0EE;</font> 配置选项</a>
					<a onclick="createTab('phpinfo')"><font>&#xF0E0;</font> 环境信息</a>
					<a href="${Globals.onlineServiceHost}" target="_blank"><font>&#xEE31;</font> 官方主页</a>
					<a onclick="logout()"><font>&#xEEDC;</font> 退出登录</a>
				</div>
			</div>`,
			initFunction: (tabId)=>{
				getTabContent(tabId, ".greetingText").innerText = `${getGreetings()}好，欢迎使用 FileAdmin ！`;
				if (getConfig("lastSaveWorkspace") && !Globals.requestedWorkspaceRestore) {
					getTabContent(tabId, ".restoreBtn").hidden = false;
				}
			},
		},
		phpinfo: {
			defaultTitle: "正在载入",
			defaultIcon: "&#xEEC9;",
			singleInstance: true,
			initHtml: `<iframe class="tabFrame"></iframe>`,
			initFunction(tabId) {
				request({action: "phpinfo"}).then( json => {
					switch (json.code) {
						case 200:
							getTabContent(tabId, "iframe").srcdoc = `<html onkeydown="event.preventDefault()">${json.info}</html>`;
							updateTab(tabId, {title: "环境信息", icon: "&#xF0E0;"});
							break;
						case 1000:
							handleAuthError();
							break;
						default:
							updateMsgBox(createMsgBox(), "信息获取失败", "出现未知错误，您所使用的环境可能不允许此操作", "error", 5000, true);
							break;
					}
				}).catch( err => {
					removeFileTab(name);
					updateMsgBox(createMsgBox(), "网络错误", "获取 PHP 信息失败", "error", 3000, true);
				});
			},
		},
		editor: {
			defaultTitle: "文本编辑器",
			defaultIcon: "&#xEEC9;",
			singleInstance: false,
			initHtml: `
				<div class="textEditorContainer"></div>
				<div class="editorMobileInput"><div class="keyboardMain"></div></div>
				<div class="editorStatusBar"></div>
			`,
			initFunction(tabId, args) {
				// 初始化编辑器底部菜单
				let editorStatusBarExtHtml = "";
				Globals.extRuntime.editorStatusBar.forEach((feature, index) => {
					if (!feature.fileExt || feature.fileExt.includes(getFileExt(args.file)))
					editorStatusBarExtHtml += `<div onclick="Globals.extRuntime.editorStatusBar[${index}].onclick()" title="${feature.description?feature.description:""}"><font>${feature.icon}</font><span>${feature.name}</span></div>`;
				});
				getTabContent(tabId, ".editorStatusBar").innerHTML = `
					<div onclick="saveFile()" title="将更改保存到服务器（Ctrl+S）"><font>&#xF0B3;</font><span>保存</span></div>
					<div onclick="refreshEditor()" title="从服务器获取最新的文件内容（F5）"><font>&#xF064;</font><span>刷新</span></div>
					<div onclick="visitPage(Globals.editors[Globals.currentTab].file)" title="使用当前浏览器访问页面"><font>&#xF0F4;</font><span>访问</span></div>
					<div onclick="locateFile(Globals.editors[Globals.currentTab].file)" title="在文件管理器中打开文件所在目录"><font>&#xED7E;</font><span>定位文件</span></div>
					<div onclick="toggleMobileInput(Globals.currentTab)" title="展开移动端符号输入面板" class="mobileInputBtn"><font>&#xEE75;</font><span>符号面板</span></div>
					<div onclick="backupFile(Globals.editors[Globals.currentTab].file)" title="为当前文件创建备份（Ctrl+B）"><font>&#xF339;</font><span>创建备份</span></div>
					<div onclick="toggleEditorWrap()" title="启用或关闭自动折行功能（Alt+Z）"><font>&#xF200;</font><span>自动折行 : <span class="wrapMode"></span></span></div>
					${editorStatusBarExtHtml}
					<div class="language" onclick="event.stopPropagation()" title="设置编辑器语法高亮" hidden><font>&#xEAE9;</font><span>编程语言 : <span class="languageText">纯文本</span></span><select class="languageSelection"></select></div>
					<div class="modifier" onclick="event.stopPropagation()" title="管理对此类文件使用的代码处理器"><font>&#xEBAD;</font><span>代码处理 : <span class="modifierText">未知</span></span><select class="modifierSelection"></select></div>
					<div class="unclickable"><font>&#xEEAD;</font><span><span class="lineNumber">1行 1列</span></span></div>
				`;
				getTabContent(tabId, ".modifierSelection").onchange = () => {
					let value = getTabContent(tabId, ".modifierSelection").value;
					let ext = getFileExt(args.file);
					let currentModifierData = getConfig("fileModifier");
					if (!value) {
						currentModifierData[ext] = "";
						setConfig("fileModifier", currentModifierData);
					}
					else {
						confirm(`要为 ${ext.toUpperCase()} 文件使用代码处理器「${fileModifier[value].uiName}」吗？\n${fileModifier[value].switchText}`, "代码处理", () => {
							currentModifierData[ext] = value;
							setConfig("fileModifier", currentModifierData);
							editorData[getConfig("textEditor")].updateConfig();
						});
					}
					editorData[getConfig("textEditor")].updateConfig();
				};
				getTabContent(tabId, ".languageSelection").onchange = () => {
					let value = getTabContent(tabId, ".languageSelection").value.split("@");
					getTabContent(tabId, ".languageSelection").value = "";
					if (value[1] == "#custom#") {
						prompt("输入编程语言名称 ...", "语法高亮", "", language => {
							getTabContent(tabId, ".languageText").innerText = value[0];
							editorData[getConfig("textEditor")].setMode(tabId, language);
						}, "输入编程语言名称，您当前使用的文本编辑器需要支持对应语言 ...");
					} else {
						getTabContent(tabId, ".languageText").innerText = value[0];
						editorData[getConfig("textEditor")].setMode(tabId, value[1]);
					}
				};
				// 初始化编辑器本体
				updateFileTab(tabId, args.file);
				Globals.editors[tabId] = editorData[getConfig("textEditor")].create(tabId, args.file, args.content);
				Globals.editors[tabId].lastSaveContent = args.content.replace(/\r\n|\r|\n/g, "\n");
				Globals.editors[tabId].file = args.file;
				Globals.editors[tabId].fileShort = args.file.split("/")[args.file.split("/").length-1] ;
				getTabContent(tabId, ".keyboardMain").innerHTML = `<div onclick="keyboardInput(this)">` + "{ } < > ( ) , ; ? ! [ ] \" ' ` / \\ % # $ = & | : * + - @ _ TAB".split(" ").join(`</div><div onclick="keyboardInput(this)">`) + `</div>`;
				getTabContent(tabId, ".editorMobileInput").onclick = () => {Globals.editors[tabId].focus();};
				getTabContent(tabId, ".editorStatusBar").onclick = () => {Globals.editors[tabId].focus();};
				updateTab(tabId, {icon: getIcon(args.file), title: Globals.editors[tabId].fileShort, tip: "文件路径：" + args.file});
				editorData[getConfig("textEditor")].resize();
				editorData[getConfig("textEditor")].updateConfig();
				loadEditorTheme();
				getTabContent(tabId).onkeydown = e => {
					if (e.key.toLowerCase() == "s" && e.ctrlKey) saveFile();
					if (e.key.toLowerCase() == "b" && e.ctrlKey) backupFile(args.file);
					if (e.key.toLowerCase() == "z" && e.altKey) toggleEditorWrap();
					if (e.key == "F5" || (e.key.toLowerCase() == "r" && e.ctrlKey)) refreshEditor();
				}
				saveLocalWorkspace();
			},
			switchFunction(tabId) {
				editorData[getConfig("textEditor")].resize();
				Globals.editors[tabId].focus();
				setTimeout(() => { Globals.editors[tabId].focus(); }, 200);
			},
			closeFunction(tabId, isForceClose) {
				if (editorData[getConfig("textEditor")].getValue(tabId) != Globals.editors[tabId].lastSaveContent && !isForceClose) {
					confirm(`文件「${Globals.editors[tabId].fileShort}」尚未保存。是否确实要放弃未保存的更改并关闭此编辑器？`, "关闭编辑器", ()=>{ closeTab(tabId, true); });
					return;
				}
				delete Globals.editors[tabId];
				removeFileTab(tabId);
				setTimeout(saveLocalWorkspace, 10);
				return true;
			}
		},
		mediaPlayer: {
			defaultTitle: "媒体播放器",
			defaultIcon: "&#xEB8F;",
			singleInstance: false,
			initHtml: `
				<video controls class="mediaPlayer" oncontextmenu="event.stopPropagation()"></video>
				<font class="audioIcon" hidden>&#xEC35;</font>
				<div class="tabLayer" hidden>
					<font>&#xECD7;</font>
					<div class="title">很抱歉，媒体加载失败</div>
					<div class="description">可能由于您的浏览器不支持此格式或文件已损坏</div>
					<a class="downloadBtn"><font>&#xEC54;</font> 下载到本地</a>
				</div>
			`,
			initFunction(tabId, file) {
				let videoElement = getTabContent(tabId, "video");
				videoElement.src = `?run=download&name=${encodeURIComponent(file)}`;
				videoElement.oncanplay = () => {
					if(!videoElement.videoHeight && !videoElement.videoWidth) {
						videoElement.style.padding = "20px";
						getTabContent(tabId, ".audioIcon").hidden = false;
					}
				};
				videoElement.onplay = () => {
					Array.from(document.getElementsByClassName("mediaPlayer")).forEach( player => {
						if(player != videoElement) player.pause();
					} );
				}
				videoElement.onerror = ()=>{
					getTabContent(tabId, ".tabLayer").hidden = false;
					getTabContent(tabId, ".downloadBtn").onclick = ()=>{ download(file); };
				};
				videoElement.play();
				updateTab(tabId, {title: file.split("/")[file.split("/").length-1]});
			},
			closeFunction(tabId) {
				removeFileTab(tabId);
				return true;
			}
		},
		imageViewer: {
			defaultTitle: "图像查看器",
			defaultIcon: "&#xEE7D;",
			singleInstance: false,
			initHtml: `
				<img class="imageViewer" ondragstart="event.preventDefault()" oncontextmenu="event.stopPropagation()">
				<div class="tabLayer" hidden>
					<font>&#xECD7;</font>
					<div class="title">很抱歉，图像加载失败</div>
					<div class="description">可能由于您的浏览器不支持此格式或文件已损坏</div>
					<a class="downloadBtn"><font>&#xEC54;</font> 下载到本地</a>
				</div>
			`,
			initFunction(tabId, file) {
				let imgElement = getTabContent(tabId, "img");
				imgElement.src = `?run=download&name=${encodeURIComponent(file)}`;
				imgElement.onerror = ()=>{
					getTabContent(tabId, ".tabLayer").hidden = false;
					getTabContent(tabId, ".downloadBtn").onclick = ()=>{ download(file); };
				};
				updateTab(tabId, {title: file.split("/")[file.split("/").length-1]});
			},
			closeFunction(tabId) {
				removeFileTab(tabId);
				return true;
			}
		},
		openWith: {
			defaultTitle: "打开方式",
			defaultIcon: "&#xED13;",
			singleInstance: false,
			initHtml: `
				<div class="openWithContainer tabLayer">
					<font class="fileIcon"></font>
					<div class="title">打开方式</div>
					<div class="container"></div>
					<div class="btns">
						<div class="checkbox" onclick="toggleCheckbox(this)">设为默认</div>
						<button onclick="confirmHandler()">打开 <font>&#xEA6C;</font></button>
					</div>
				</div>
			`,
			initFunction(tabId, file) {
				getTabContent(tabId, ".fileIcon").innerHTML = getIcon(file);
				getTabContent(tabId, ".openWithContainer").dataset.file = file;
				let handlers = getFileHandler(file);
				if (handlers.firstHandler && fileHandlers[handlers.firstHandler].uiName) getTabContent(tabId, ".container").innerHTML += `<div onclick="selectHandler('${handlers.firstHandler}', this)" ondblclick="confirmHandler()"><font>&#x${fileHandlers[handlers.firstHandler].icon};</font><span>${fileHandlers[handlers.firstHandler].uiName}<small>${getConfig("defaultFileHandler")[getFileExt(file)] != handlers.firstHandler ? "最适合处理此类文件" : "已设为默认打开方式"}</small></span></div>`;
				handlers.fileSupportedList.forEach(handlerName => {
					if (handlerName != handlers.firstHandler) getTabContent(tabId, ".container").innerHTML += `<div onclick="selectHandler('${handlerName}', this)" ondblclick="confirmHandler()"><font>&#x${fileHandlers[handlerName].icon};</font><span>${fileHandlers[handlerName].uiName}</span></div>`;
				});
				if (handlers.fullSupportedList.length) {
					handlers.fullSupportedList.forEach(handlerName => {
						if (handlerName != handlers.firstHandler) getTabContent(tabId, ".container").innerHTML += `<div class="folded" onclick="selectHandler('${handlerName}', this)" ondblclick="confirmHandler()"><font>&#x${fileHandlers[handlerName].icon};</font><span>${fileHandlers[handlerName].uiName}</span></div>`;
					});
					getTabContent(tabId, ".container").innerHTML += `<a onclick="showAllHanders()"><font>&#xEA4C;</font> 展开全部打开方式</a>`;
					if (!getTabContent(tabId, ".container>div:not(.folded)")) setTimeout(() => { getTabContent(tabId, ".container>a").click(); }, 1);
				}
				getTabContent(tabId, ".container>div").click();
				getTabContent(tabId, ".checkbox").click();
				updateTab(tabId, {title: file.split("/")[file.split("/").length-1]});
			},
			closeFunction(tabId) {
				removeFileTab(tabId);
				return true;
			}
		},
		settings: {
			defaultTitle: "选项",
			defaultIcon: "&#xF0EE;",
			singleInstance: true,
			initHtml: `<div class="settingsContainer"></div>`,
			initFunction(tabId) {
				Globals.configTabId = tabId;
				renderConfigTab();
			}
		},
		extensions: {
			defaultTitle: "正在载入",
			defaultIcon: "&#xEEC9;",
			singleInstance: true,
			initHtml: `<iframe class="tabFrame"></iframe>`,
			initFunction(tabId) {
				let frame = getTabContent(tabId, "iframe");
				frame.src = `${Globals.onlineServiceHost}/backend/extension/?isDark=` + loadUiTheme(true);
				frame.onload = () => {
					updateTab(tabId, {title: "扩展市场", icon: "&#xEA44;"});
					frame.contentWindow.postMessage({fileadminLoadedExt: getConfig("loadedExtensions")}, "*");
				}
			}
		},
		totp: {
			defaultTitle: "二步验证配置",
			defaultIcon: "&#xEED0;",
			singleInstance: true,
			initHtml: `
				<div class="settingsContainer">
					<div class="title"><font>&#xEED0;</font> 二步验证</div>
					<div class="config totpStatusBox">
						<div class="title">开启状态</div>
						<div class="description">当前二步验证状态：<span class="totpStatus">获取中</span>。</div>
						<button class="totpOnBtn" hidden onclick="enableTotp()">配置 TOTP 二步验证</button>
						<button class="sub totpOffBtn" hidden onclick="disableTotp()">关闭 TOTP 二步验证</button>
					</div>
					<div class="config totpConfigurationBox" hidden>
						<div class="title">第一步：录入信息</div>
						<div class="description">使用您的 TOTP 验证器应用扫描下方二维码。</div>
						<div class="totpQrcode"></div>
						<div class="title">第二步：确认生效</div>
						<div class="description">在下方输入 TOTP 验证器应用显示的六位验证码。</div>
						<input autocompelete="off" class="totpConfirmInput" type="number"><br>
						<button onclick="confirmTotp()">开启 TOTP 二步验证</button>
					</div>
					<div class="config">
						<div class="title">关于此功能</div>
						<div class="description">开启 TOTP 二步验证有助于进一步确保您的主机安全，其是目前最通用、经济的安全认证方式。使用此功能时，您需要确保您本地与服务器的时间同步以免出现验证码不匹配的问题。</div>
					</div>
				</div>`,
			initFunction(tabId) {
				getTotpStatus(tabId);
			}
		},
		donate: {
			defaultTitle: "捐助",
			defaultIcon: "&#xEA92;",
			singleInstance: true,
			initHtml: `
				<div class="tabLayer" ondragstart="event.preventDefault()">
					<font>&#xEA92;</font>
					<div class="title">帮助我们走下去</div>
					<a href="https://i.simsv.com/#donate" target="_blank">
						<img src="https://asset.simsoft.top/donate/donate-new.png" class="donateImage">
					</a>
					<br>
					<div class="description">
						FileAdmin 是一款没有盈利的非商业产品<br>
						项目的维护者也是没有稳定收入的在校学生<br>
						开发不易 若您有能力 请捐助我们<br>
						这将是我们保持维护的最大动力 非常感谢
					</div>
				</div>
			`,
		},
		upload: {
			defaultTitle: "文件上传 (0/0)",
			defaultIcon: "&#xF24A;",
			singleInstance: false,
			initHtml: `<div class="filesContainer"></div>`,
			initFunction(tabId, files) {
				files.forEach( (file, index) => {
					let uploadDir = Globals.currentDir;
					if (file.webkitRelativePath) uploadDir += formatDir(file.webkitRelativePath.substring(0, file.webkitRelativePath.lastIndexOf("/")));
					let fileDiv = document.createElement("div");
					fileDiv.dataset.fileIndex = index;
					fileDiv.innerHTML = `
						<div class="background"></div>
						<div class="progressBar"></div>
						<div class="name"><font>${getIcon(file.name)}</font> ${textEscape(file.name)}</div>
						<div class="info">
							<div><font>&#xED72;</font> 根目录${textEscape(uploadDir).replaceAll("/","<font>&#xEA54;</font>")}</div>
							<div><font>&#xED0D;</font> <span class="trunkInfo">等待上传</span></div>
							<div><font>&#xF20F;</font> <span class="progressInfo">0%</span></div>
						</div>
					`;
					getTabContent(tabId, ".filesContainer").appendChild(fileDiv);
				} );
			},
			closeFunction(tabId) {
				if (Globals.upload[tabId]) {
					updateMsgBox(createMsgBox(), "任务进行中", "请等待进行中的上传任务完成后继续操作", "error", 3000, true);
					return;
				}
				return true;
			}
		},
		search: {
			defaultTitle: "文件搜索",
			defaultIcon: "&#xF0D1;",
			singleInstance: false,
			initHtml: `
				<div class="searchContainer">
					<div class="title"><font>&#xED05;</font> 文件搜索</div>
					<div class="searchOptions">
						<div><span><font>&#xEC9D;</font> 工作模式</span><div><select name="mode"><option value="searchName">搜索文件名</option><option value="searchContent">搜索文件内容</option><option value="replaceContent">替换文件内容</option></select></div></div>
						<div><span><font>&#xF0D1;</font> 搜索内容</span><div><input autocomplete="off" name="search" placeholder="输入搜索内容，不可为空 ..."></div></div>
						<div class="replaceContent" hidden><span><font>&#xECD3;</font> 替换内容</span><div><input autocomplete="off" name="replace" placeholder="输入替换内容 ..."></div></div>
						<div><span><font>&#xED5A;</font> 搜索目录</span><div><input autocomplete="off" name="dir" placeholder="留空即为根目录 ..."><font class="dirSelectBtn">&#xECAF;</font></div></div>
						<div><span><font>&#xED8D;</font> 大小写敏感</span><div><select name="case"><option value="on">开启</option><option value="off">关闭</option></select></div></div>
						<div><span><font>&#xED8A;</font> 包含子目录</span><div><select name="sub"><option value="on">开启</option><option value="off">关闭</option></select></div></div>
						<div><span><font>&#xED0F;</font> 限制后缀名</span><div><input autocomplete="off" name="ext" placeholder="空格分隔，留空即为不限 ..."></div></div>
						<div class="btnContainer"><button class="searchBtn"><font>&#xF0D1;</font> 搜索文件</button><span></span><button class="replaceBtn" disabled><font>&#xF1FF;</font> 替换文本</button></div>
					</div>
					<div class="searchResult">
						<center>您还没有发起搜索 ~</center>
					</div>
				</div>`,
			initFunction(tabId, searchDir) {
				getTabContent(tabId, "[name='dir']").value = searchDir;
				getTabContent(tabId, "[name='dir']").onchange = () => { getTabContent(tabId, "[name='dir']").value = formatDir(getTabContent(tabId, "[name='dir']").value); getTabContent(tabId, ".replaceBtn").disabled = true; }
				getTabContent(tabId, "[name='search']").onchange = () => { getTabContent(tabId, ".replaceBtn").disabled = true; }
				getTabContent(tabId, "[name='case']").onchange = () => { getTabContent(tabId, ".replaceBtn").disabled = true; }
				getTabContent(tabId, "[name='sub']").onchange = () => { getTabContent(tabId, ".replaceBtn").disabled = true; }
				getTabContent(tabId, "[name='ext']").onchange = () => { getTabContent(tabId, ".replaceBtn").disabled = true; }
				getTabContent(tabId, ".dirSelectBtn").onclick = () => {
					pickDir("搜索目录", getTabContent(tabId, "[name='dir']").value, dir => {
						getTabContent(tabId, "[name='dir']").value = dir;
					});
				};
				getTabContent(tabId, "[name='mode']").onchange = () => {
					if (getTabContent(tabId, "[name='mode']").value == "replaceContent") getTabContent(tabId, ".replaceContent").hidden = false;
					else getTabContent(tabId, ".replaceContent").hidden = true;
					getTabContent(tabId, ".replaceBtn").disabled = true;
				}
				getTabContent(tabId, ".searchBtn").onclick = () => {
					let boxId = createMsgBox();
					updateMsgBox(boxId, "正在搜索", "正在搜索文件，可能需要一段时间，请稍候 ...", "loading");
					getTabContent(tabId, ".searchBtn").disabled = true;
					request({
						action: "search",
						dir: formatDir(getTabContent(tabId, "[name='dir']").value),
						search: getTabContent(tabId, "[name='search']").value,
						mode: getTabContent(tabId, "[name='mode']").value=="searchName"?"searchName":"searchContent",
						subDir: getTabContent(tabId, "[name='sub']").value,
						caseSensitive: getTabContent(tabId, "[name='case']").value,
						ext: getTabContent(tabId, "[name='ext']").value.toLowerCase(),
					}).then(json => {
						getTabContent(tabId, ".searchBtn").disabled = false;
						let searchResult = getTabContent(tabId, ".searchResult");
						searchResult.innerHTML = "";
						switch(json.code) {
							case 200:
								hideMsgBox(boxId);
								json.results.forEach(result => {
									let div = document.createElement("div");
									div.onclick = () => { openFile(result.name, null, false); }
									let linesHtml = "";
									if (result.search) {
										linesHtml += "<div class='lines' onclick='event.stopPropagation()'>";
										result.search.forEach(line => {
											linesHtml += `<div><span>${line[0]}</span><span>${textEscape(line[1].trim())}</span></div>`;
										});
										linesHtml += "</div>";
									}
									div.innerHTML = `<div class="name"><font>${getIcon(result.name)}</font> ${textEscape(result.name)}</div>${linesHtml}`;
									searchResult.appendChild(div);
								});
								if (getTabContent(tabId, "[name='mode']").value == "replaceContent") getTabContent(tabId, ".replaceBtn").disabled = false;
								break;
							case 1000:
								handleAuthError(boxId);
								break;
							case 1001:
								hideMsgBox(boxId);
								searchResult.innerHTML = `<center>搜索目录不存在，请检查后再试</center>`;
								break;
							case 1002:
								hideMsgBox(boxId);
								searchResult.innerHTML = `<center>未搜索到任何结果</center>`;
								break;
							default:
								updateMsgBox(boxId, "未知错误", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
								break;
						}
						
					}).catch(err => {
						updateMsgBox(boxId, "搜索失败", "网络错误，请检查网络连接", "error", 3000, true);
						getTabContent(tabId, ".searchBtn").disabled = false;
					})
				}
				getTabContent(tabId, ".replaceBtn").onclick = () => {
					confirm("确实要执行替换吗？\n替换操作不可被撤销，请先核对文件内容后继续操作。", "替换文本", () => {
						let boxId = createMsgBox();
						updateMsgBox(boxId, "正在替换", "正在替换文件内容，可能需要一段时间，请稍候 ...", "loading");
						getTabContent(tabId, ".replaceBtn").disabled = true;
						getTabContent(tabId, ".searchBtn").disabled = true;
						request({
							action: "search",
							dir: formatDir(getTabContent(tabId, "[name='dir']").value),
							search: getTabContent(tabId, "[name='search']").value,
							replace: getTabContent(tabId, "[name='replace']").value,
							mode: "replaceContent",
							subDir: getTabContent(tabId, "[name='sub']").value,
							caseSensitive: getTabContent(tabId, "[name='case']").value,
							ext: getTabContent(tabId, "[name='ext']").value.toLowerCase(),
						}).then(json => {
							getTabContent(tabId, ".searchBtn").disabled = false;
							getTabContent(tabId, ".searchBtn").click();
							switch(json.code) {
								case 200:
									updateMsgBox(boxId, "替换成功", `已在 ${json.total} 个文件中完成替换`, "success", 3000, true);
									break;
								case 1000:
									handleAuthError(boxId);
									break;
								case 1003:
									updateMsgBox(boxId, "替换时出现问题", `共 ${json.total} 个文件中 ${json.total - json.success} 个替换失败，请检查文件权限配置`, "info", 3000, true);
									break;
								default:
									updateMsgBox(boxId, "未知错误", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
									break;
							}
						}).catch(err => {
							updateMsgBox(boxId, "替换失败", "网络错误，请检查网络连接", "error", 3000, true);
							getTabContent(tabId, ".searchBtn").disabled = false;
						})
					}, true);
				}
			}
		},
		analyse: {
			defaultTitle: "目录分析",
			defaultIcon: "&#xEFFA;",
			singleInstance: false,
			initHtml: `
				<div class="analyseContainer">
					<div class="title"><font>&#xEFF6;</font> 目录分析</div>
					<div class="box diskStatContainer">
						<div class="title"><font>&#xEDF9;</font> 磁盘占用</div>
						<div class="progress"><div class="dirPercentProgress"></div><div class="usedPercentProgress"></div></div>
						<div class="dataContainer diskPercentText"></div>
					</div>
					<div class="box">
						<div class="title"><font>&#xED8A;</font> 占用详情</div>
						<div class="dirDetail"></div>
					</div>
				</div>
			`,
			initFunction(tabId, data) {
				if (!data.diskTotal || !data.diskFree) getTabContent(tabId, ".diskStatContainer").hidden = true;
				else {
					let dirPercent = ( Math.round(data.dirSize / data.diskTotal * 10000) / 100 || 0.01 ) + "%";
					let usedPercent = ( Math.round((data.diskTotal - data.diskFree) / data.diskTotal * 10000) / 100 || 0.01 ) + "%";
					getTabContent(tabId, ".dirPercentProgress").style.width = dirPercent;
					getTabContent(tabId, ".usedPercentProgress").style.width = usedPercent;
					getTabContent(tabId, ".diskPercentText").innerHTML = `<span>当前目录：${humanSize(data.dirSize)}</span><span>磁盘已用：${humanSize(data.diskTotal - data.diskFree)}</span><span>磁盘空间：${humanSize(data.diskTotal)}</span>`;
				}
				data.detail.sort(function(a, b) { return b.size - a.size; });
				data.detail.forEach(fileData => {
					if (getConfig("showHiddenFiles") == "on" || (fileData.fullName != "/.FileAdmin" && fileData.fullName != "/" + Globals.faScript)) {
						getTabContent(tabId, ".dirDetail").innerHTML += `
							<div class="progress file">
								<div><font>${getIcon(fileData.name, fileData.isDir)}</font> ${fileData.name}</div>
								<div style="width:${Math.round(fileData.size / data.dirSize * 10000) / 100}%"></div>
							</div>
							<div class="dataContainer">
								<span>文件大小：${humanSize(fileData.size)}</span>
								<span>目录占用：${Math.round(fileData.size / data.dirSize * 10000) / 100 || 0.01}%</span>
								<span>${data.diskTotal?`磁盘占用：${Math.round(fileData.size / data.diskTotal * 10000) / 100 || 0.01}%`:"无法读取磁盘占用"}</span>
							</div>
						`;
					}
				});
				if (!getTabContent(tabId, ".dirDetail").innerHTML) {
					closeTab(tabId);
					quickMsgBox("当前目录仅存在 FileAdmin 本体文件及其数据，无法进行分析。");
				}
			}
		}
	}
	function createTab(tabType, args) {
		let currentTabData = tabData[tabType];
		if (!currentTabData) return;
		if (currentTabData.singleInstance) { for (oldTabId in Globals.tabs) if (Globals.tabs[oldTabId] == tabType) return switchTab(oldTabId); }
		setTimeout(() => {selectFile(2);} ,10);
		let tabId = new Date().getTime() + "-" + Math.round( Math.random() * 114514 );
		ID("userTabsContainer").innerHTML += `
			<div class="tab" id="FileAdminTab-${tabId}" onmouseup="handleTabClick('${tabId}')" onpointerdown="switchTab('${tabId}',event)" draggable="true" ondragstart="handleTabsDragStart(event,this)" ondragover="handleTabsDragOver(event,this)" ondrop="handleTabsDrop(event,this)" ondragend="handleTabsDragEnd(this)" title="${currentTabData.defaultTip?currentTabData.defaultTip:""}">
				<font class="icon">${currentTabData.defaultIcon}</font>
				<span class="title">${textEscape(currentTabData.defaultTitle)}</span>
				<font class="close" onclick="closeTab('${tabId}')">&#xEB99;</font>
			</div>
		`;
		Globals.tabs[tabId] = tabType;
		let contentDiv = document.createElement("div");
		contentDiv.innerHTML = currentTabData.initHtml;
		contentDiv.id = "FileAdminTabContent-" + tabId;
		contentDiv.tabIndex = 0;
		ID("tabsContent").appendChild(contentDiv);
		if (currentTabData.initFunction) try{ 
			currentTabData.initFunction(tabId, args);
		} catch (error) {
			updateMsgBox(createMsgBox(), "运行错误", "初始化标签页时出现错误，您可以向我们反馈此问题", "error", 3000, true);
			console.warn(error);
		}
		switchTab(tabId);
		execExtHook("tabcreate", tabId, tabType, args, contentDiv);
		return tabId;
	}
	function closeTab(tabId, forceClose, skipCloseFunction) {
		try{
			if (Object.keys(Globals.tabs).length == 1 && Globals.tabs[tabId] == "welcome") return quickMsgBox("请至少保留一个标签页");
			if (!skipCloseFunction && tabData[Globals.tabs[tabId]].closeFunction) if(!tabData[Globals.tabs[tabId]].closeFunction(tabId, forceClose) && !forceClose) return;
			if (Object.keys(Globals.tabs).length == 1) createTab("welcome", null, true);
			if (tabId == Globals.currentTab) {
				let previous = ID("FileAdminTab-" + tabId).previousElementSibling;
				let next = ID("FileAdminTab-" + tabId).nextElementSibling;
				let switchElement = previous ? previous : next;
				switchTab(switchElement.id.substring(13), null, true);
			}
			ID("FileAdminTab-" + tabId).remove();
			ID("FileAdminTabContent-" + tabId).remove();
			execExtHook("tabclose", tabId);
			delete Globals.tabs[tabId];
		} catch(err) {
			console.warn(err);
			confirm("关闭此标签页时出现错误，按「确定」以尝试对其进行强制关闭。\n这可能导致 FileAdmin 运行不稳定或丢失标签页中的数据。", "出现错误", () => {
				closeTab(tabId, true, true);
			});
		}
	}
	function switchTab(tabId, e, notUserGesture) {
		if(e) Globals.lastTabClickPosition = e.clientX;
		if(e && ( e.target.classList.contains("close") || e.button==1 )) return;
		if(!ID("FileAdminTab-" + tabId)) return;
		if(Globals.tabs[tabId] != "welcome" && !notUserGesture) toggleFullMode(true);
		if(ID("userTabsContainer").getElementsByClassName("active")[0]) ID("userTabsContainer").getElementsByClassName("active")[0].classList.remove("active");
		if(document.querySelector("#tabsContent>.active")) document.querySelector("#tabsContent>.active").classList.remove("active");
		ID("FileAdminTab-" + tabId).classList.add("active");
		ID("FileAdminTab-" + tabId).scrollIntoView({ inline: "center", behavior: "smooth" });
		document.title = ID("FileAdminTab-" + tabId).getElementsByClassName("title")[0].innerText + " - FileAdmin";
		ID("FileAdminTabContent-" + tabId).classList.add("active");
		ID("FileAdminTabContent-" + tabId).focus();
		if(tabData[Globals.tabs[tabId]].switchFunction) tabData[Globals.tabs[tabId]].switchFunction(tabId);
		Globals.currentTab = tabId;
		updateFileListStatus();
		execExtHook("tabswitch", tabId);
	}
	function updateTab(tabId, data) {
		if (!data) return;
		let tabElement = ID("FileAdminTab-" + tabId);
		if (data.icon) tabElement.getElementsByClassName("icon")[0].innerHTML = data.icon;
		if (data.tip) tabElement.title = data.tip;
		if (data.title) tabElement.getElementsByClassName("title")[0].innerHTML = textEscape(data.title);
		if (data.closeIcon) tabElement.getElementsByClassName("close")[0].innerHTML = data.closeIcon;
		if (data.title && tabId == Globals.currentTab) document.title = data.title + " - FileAdmin";
		execExtHook("tabupdate", tabId, data);
	}
	function handleTabClick(tabId) {
		ID("tabs").click();
		if (event.button == 1 && Globals.lastTabClickPosition == event.clientX) {
			event.preventDefault();
			closeTab(tabId);
		}
	}
	function getTabContent(tabId, selector) {
		if(selector) return ID("FileAdminTabContent-" + tabId).querySelector(selector);
		else return ID("FileAdminTabContent-" + tabId);
	}
	function toggleFullMode(isMobileAction) {
		if (isMobileAction && document.documentElement.clientWidth > 600) return;
		if (event) event.stopPropagation();
		if (document.documentElement.classList.contains("fullMode") && !isMobileAction) {
			document.documentElement.classList.remove("fullMode");
			ID("files").focus();
			execExtHook("fullmode", false);
		} else {
			document.documentElement.classList.add("fullMode");
			ID("tabs").focus();
			execExtHook("fullmode", true);
		}
		let resizeInterval = setInterval(editorData[getConfig("textEditor")].resize, 1);
		setTimeout(resizeInterval, 300);
	}
	function handleTabsKeydown() {
		if (event.key.toLowerCase() == "w" && event.ctrlKey) { event.preventDefault(); closeTab(Globals.currentTab); }
	}
	function handleTabsDragStart(e, element) {
		Globals.dragTabSrcElement = element;
		e.dataTransfer.effectAllowed = "move";
		e.dataTransfer.setData("text/html", element.outerHTML);
	}
	function handleTabsDragOver(e, element) {
		e.preventDefault();
		e.stopPropagation();
		if (Globals.dragTabSrcElement) e.dataTransfer.dropEffect = "move";
		else element.onpointerdown();
	}
	function handleTabsDrop(e, element) {
		e.stopPropagation();
		if (Globals.dragTabSrcElement !== element) {
			Globals.dragTabSrcElement.outerHTML = element.outerHTML;
			element.outerHTML = e.dataTransfer.getData("text/html");
		}
		Globals.dragTabSrcElement = null;
	}
	function handleTabsDragEnd(element) {
		Globals.dragTabSrcElement = null;
	}
	
	// 欢迎页
	function getGreetings() {
		let currentTime = new Date();
		let currentHour = currentTime.getHours();
		let greeting;
		switch (true) {
			case currentHour >= 6 && currentHour < 12: greeting = "早上"; break;
			case currentHour >= 12 && currentHour < 14: greeting = "中午"; break;
			case currentHour >= 14 && currentHour < 18: greeting = "下午"; break;
			case currentHour >= 18 && currentHour < 24: greeting = "晚上"; break;
			default: greeting = "凌晨"; break;
		}
		return greeting;
	}
	
	// 文件管理页
	function formatDir(raw) {
		let formatted = raw.trim();
		formatted = formatted.replace(/\\/g, '/');
		formatted = formatted.replace(/\/+/g, '/');
		if (formatted.charAt(0) !== '/') formatted = '/' + formatted;
		if (formatted.charAt(formatted.length - 1) === '/') formatted = formatted.slice(0, -1);
		return formatted;
	}
	function humanSize(size) {
		const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
		let index = 0;
		while (size >= 1024 && index < units.length - 1) { size /= 1024; index++; }
		return size.toFixed(2) + units[index];
	}
	function isSameDay(timestamp1, timestamp2) {
		const date1 = new Date(timestamp1);
		const date2 = new Date(timestamp2);
		return (
			date1.getFullYear() == date2.getFullYear() &&
			date1.getMonth() == date2.getMonth() &&
			date1.getDate() == date2.getDate()
		);
	}
	function humanTime(timestamp) {
		let now = Date.now();
		let date = new Date(timestamp);
		let diff = (now - timestamp) / 1000;
		if (diff < 300) return '刚刚';
		if (diff < 3600) return `${Math.floor(diff / 60)} 分钟前`;
		if (diff < 43200) return`${Math.floor(diff / 3600)} 小时前`;
		if (isSameDay(now, timestamp)) return '今天';
		if (!Math.floor(diff / 86400)) return '昨天';
		if (diff < 432000) return `${Math.floor(diff / 86400)} 天前`;
		return `${date.getFullYear()}.${('0' + (date.getMonth() + 1)).slice(-2)}.${('0' + date.getDate()).slice(-2)}`;
	}
	function getFileExt(file) {
		if (!file.endsWith(".bak")) return file.split(".")[file.split(".").length-1].toLowerCase();
		return file.split(".")[file.split(".").length-3].toLowerCase();
	}
	function getFileList(dir = Globals.currentDir) {
		if (event) event.stopPropagation();
		if (Globals.isGettingFileList) return;
		Globals.isGettingFileList = true;
		Globals.currentDir = formatDir(dir);
		Globals.selectedFiles = [];
		Globals.isSelecting = false;
		updateFileListStatus();
		dir = formatDir(dir);
		ID("fileLoading").hidden = false;
		request({action: "dir", dir: dir}).then( json => {
			Globals.isGettingFileList = false;
			ID("fileLoading").hidden = true;
			renderFileAddress();
			refreshIndicator.style.height = '0';
			switch (json.code) {
				case 200:
					renderFileList(json.files);
					Globals.currentDirInfo = json.files;
					if (dir) saveLocalWorkspace();
					break;
				case 1000:
					handleAuthError();
					break;
				case 1001:
					updateMsgBox(createMsgBox(), "目录读取失败", "您尝试打开的目录已被删除", "error", 3000, true);
					getFileList("");
					break;
				default:
					updateMsgBox(createMsgBox(), "目录读取失败", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
					break;
			}
		}).catch( err => {
			refreshIndicator.style.height = '0';
			renderFileAddress();
			Globals.isGettingFileList = false;
			ID("fileLoading").hidden = true;
			updateMsgBox(createMsgBox(), "网络错误", "获取文件列表失败", "error", 3000, true);
			ID("fileList").innerHTML = "<center><font>&#xED7F;</font>网络错误，请稍候<a onclick='getFileList()'>刷新</a>列表</center>";
		});
	}
	function renderFileList(fileArray, customRenderElement, customFileClick) {
		let sortResult = fileArray.map(file => ({ file, weights: file.name.match(/[^\d]+|\d+/g) })).sort((a, b) => {
			let pos = 0;
			const weightsA = a.weights;
			const weightsB = b.weights;
			let weightA = weightsA[pos];
			let weightB = weightsB[pos];
			while (weightA && weightB) {
				const v = weightA - weightB;
				if (!isNaN(v) && v !== 0) return v;
				if (weightA !== weightB) return weightA > weightB ? 1 : -1;
				pos += 1;
				weightA = weightsA[pos];
				weightB = weightsB[pos];
			}
			return weightA ? 1 : -1;
		});
		fileArray = sortResult.map(x => x.file);
		fileArray.sort((a, b) => {
			if (a.isDir && !b.isDir) return -1;
			if (!a.isDir && b.isDir) return 1;
			else return 0;
		});
		if (!customRenderElement) customRenderElement = ID("fileList");
		customRenderElement.innerHTML = "";
		fileArray.forEach( fileInfo => {
			if (getConfig("showHiddenFiles") == "on" || (fileInfo.name != "/.FileAdmin" && fileInfo.name != "/" + Globals.faScript)) {
				let fileDiv = document.createElement("div"); 
				fileDiv.dataset.name = fileInfo.name;
				fileDiv.innerHTML = `<font>${getIcon(fileInfo.name, fileInfo.isDir)}</font><span class="name">${textEscape(fileInfo.name.split("/")[fileInfo.name.split("/").length - 1])}</span>`;
				if(!fileInfo.isDir) fileDiv.innerHTML += `<span class="size">${humanSize(fileInfo.size)}</span>`;
				if(customRenderElement == ID("fileList")) {
					fileDiv.onclick = () => { 
						if(Globals.isTouchDevice) {
							if (Globals.selectedFiles.length) selectToggle(fileInfo.name);
							else openFile(fileInfo.name, null, fileInfo.isDir);
						} else {
							if(!event.ctrlKey) Globals.selectedFiles = [fileInfo.name];
							else selectToggle(fileInfo.name);
							updateFileListStatus();
						}
					};
					fileDiv.ondblclick = () => { if(!Globals.isTouchDevice && !event.ctrlKey && !event.shiftKey) openFile(fileInfo.name, null, fileInfo.isDir); };
					fileDiv.oncontextmenu = e => {
						e.stopPropagation();
						if(Globals.isTouchDevice && !Globals.selectedFiles.includes(fileInfo.name)) Globals.selectedFiles = [fileInfo.name];
						else if (Globals.selectedFiles.includes(fileInfo.name)) showFileMenu();
						else { Globals.selectedFiles = [fileInfo.name]; showFileMenu(); }
						updateFileListStatus();
					};
				} else if(customFileClick) {
					fileDiv.onclick = () => { customFileClick(fileInfo.name, fileInfo.isDir, fileDiv); };
				}
				customRenderElement.appendChild(fileDiv);
			}
		});
		if(!customRenderElement.innerHTML) customRenderElement.innerHTML = "<center><font>&#xED7F;</font>当前打开的目录为空</center>";
		updateFileListStatus();
	}
	function renderFileAddress(renderTarget = ID("fileAddressText"), dirNames = Globals.currentDir, callback = getFileList) {
		renderTarget.innerHTML = "";
		let rootDirDiv = document.createElement("div");
		rootDirDiv.innerText = "根目录";
		rootDirDiv.onclick = () => { callback("") }
		renderTarget.appendChild(rootDirDiv);
		let currentDirHtml = "";
		dirNames.split("/").forEach( dirName => {
			if (dirName) {
				currentDirHtml += "/" + dirName;
				let font = document.createElement("font");
				font.innerHTML = "&#xF2E5;";
				renderTarget.appendChild(font);
				let div = document.createElement("div");
				div.dataset.dir = currentDirHtml;
				div.onclick = () => { callback(div.dataset.dir); };
				div.innerText = dirName;
				renderTarget.appendChild(div);
				div.scrollIntoView({ block: "end" });
			}
		} );
	}
	function getPrevDir() {
		let prevDir = Globals.currentDir.substring(0, Globals.currentDir.lastIndexOf("/"));
		if (prevDir == Globals.currentDir) return quickMsgBox("您正在查看根目录");
		getFileList(prevDir);
	}
	function editPath(callback = getFileList) {
		prompt("请输入路径 ...", "转到", Globals.currentDir, callback);
	}
	function updateFileListStatus() {
		Globals.selectedFiles = Globals.selectedFiles.filter((item, index, array) => { return array.indexOf(item) === index; });
		if(ID("fileList").getElementsByClassName("editorActive")[0]) ID("fileList").getElementsByClassName("editorActive")[0].classList.remove("editorActive");
		Array.from(ID("fileList").getElementsByClassName("selected")).forEach( element => { element.classList.remove("selected"); } );
		if(!Globals.selectedFiles.length) {
			for(let name in Globals.fileTabs) {
				if (Globals.fileTabs[name].tabId == Globals.currentTab) {
					Array.from(ID("fileList").getElementsByTagName("div")).forEach(element => {
						if(element.dataset.name == name) element.classList.add("editorActive");
					});
				}
			}
		} else {
			for (let index in Array.from(ID("fileList").getElementsByTagName("div"))) {
				let element = Array.from(ID("fileList").getElementsByTagName("div"))[index];
				if(Globals.selectedFiles.indexOf(element.dataset.name) != -1) element.classList.add("selected");
				else element.classList.remove("selected");
			}
		}
		Array.from(document.querySelectorAll("#fileMenu>div")).forEach( element => { element.hidden = true; } );
		switch (Globals.selectedFiles.length) {
			case 0: ID("fileMenuNone").hidden = false; break;
			case 1:
				ID("fileMenuSingle").hidden = false;
				Globals.currentDirInfo.forEach( fileData => {
					if(fileData.name == Globals.selectedFiles[0]){
						if(fileData.isDir) Array.from(ID("fileMenuSingle").getElementsByClassName("fileOnlyBtn")).forEach( element => { element.hidden = true; } );
						else Array.from(ID("fileMenuSingle").getElementsByClassName("fileOnlyBtn")).forEach( element => { element.hidden = false; } );
					}
				} );
			break;
			default: ID("fileMenuMultiple").hidden = false; break;
		}
	}
	function showFileMenu() {
		if (event) event.preventDefault();
		let evt = event;
		setTimeout(() => {
			ID("fileMenu").classList.add("active");
			ID("fileMenu").style.left = evt.pageX + "px";
			if (evt.pageY + ID("fileMenu").clientHeight + 14 < document.documentElement.clientHeight) {
				ID("fileMenu").style.transformOrigin = "top left";
				ID("fileMenu").style.bottom = "unset";
				ID("fileMenu").style.top = evt.pageY + "px";
			} else if (evt.pageY - ID("fileMenu").clientHeight - 59 > 0) {
				ID("fileMenu").style.transformOrigin = "bottom left";
				ID("fileMenu").style.top = "unset";
				ID("fileMenu").style.bottom = document.documentElement.clientHeight - evt.pageY + "px";
			} else {
				ID("fileMenu").style.transformOrigin = "top left";
				ID("fileMenu").style.top = evt.pageY + "px";
				ID("fileMenu").style.bottom = "5px";
			}
		}, 50);
	}
	function hideFileMenu() {
		ID("fileMenu").classList.remove("active");
		ID("fileMenu").style.top = "unset";
		ID("fileMenu").style.bottom = "unset";
	}
	ID("fileList").onmousedown = event => {
		hideFileMenu();
		if (event.button != 0) return;
		Globals.isSelecting = true;
		Globals.isSelectingWithKey = event.ctrlKey || event.shiftKey;
		if (event.target == ID("fileList") && !event.ctrlKey) selectFile(2);
		ID("files").focus();
		updateFileListStatus();
		let { clientX, clientY } = event;
		Globals.selectionStartX = clientX;
		Globals.selectionStartY = clientY;
		Globals.selectionStartFiles = Globals.selectedFiles;
		ID("fileSelectionBox").style.left = Globals.selectionStartX + "px";
		ID("fileSelectionBox").style.top = Globals.selectionStartY + "px";
		ID("fileSelectionBox").style.width = "0";
		ID("fileSelectionBox").style.height = "0";
		ID("fileSelectionBox").hidden = false;
		event.preventDefault();
	};
	ID("fileList").onmousemove = event => {
		if (Globals.isSelecting) {
			let { clientX, clientY } = event;
			let left = Math.min(Globals.selectionStartX, clientX);
			let top = Math.min(Globals.selectionStartY, clientY);
			let width = Math.abs(clientX - Globals.selectionStartX);
			let height = Math.abs(clientY - Globals.selectionStartY);
			ID("fileSelectionBox").style.left = left + "px";
			ID("fileSelectionBox").style.top = top + "px";
			ID("fileSelectionBox").style.width = width + "px";
			ID("fileSelectionBox").style.height = height + "px";
			let fileDivs = Array.from(ID("fileList").getElementsByTagName("div"));
			if (!Globals.isSelectingWithKey) selectFile(2);
			fileDivs.forEach((fileDiv) => {
				let rect = fileDiv.getBoundingClientRect();
				if (
					rect.left < left + width &&
					rect.left + rect.width > left &&
					rect.top < top + height &&
					rect.top + rect.height > top
				) Globals.selectedFiles.push(fileDiv.getAttribute("data-name"));
			});
			updateFileListStatus();
		}
	};
	document.addEventListener("mouseup", () => {
		Globals.isSelecting = false;
		ID("fileSelectionBox").hidden = true;
		updateFileListStatus();
	});
	ID("fileList").ontouchstart = e => {
		if (!ID("fileList").scrollTop) Globals.slideFreshStart = e.touches[0].clientY;
	};
	ID("fileList").ontouchmove = e => {
		if (!Globals.slideFreshStart) return;
		let distance = e.touches[0].clientY - Globals.slideFreshStart;
		let refreshIndicator = ID("refreshIndicator");
		if (distance > 0 && ID("fileList").scrollTop === 0) {
			e.preventDefault();
			refreshIndicator.style.transition = 'none';
			refreshIndicator.style.height = `${distance}px`;
			if (distance > 80) refreshIndicator.innerText = '释放刷新';
			else refreshIndicator.innerText = '下拉刷新';
		}
	};
	ID("fileList").ontouchend = () => {
		let refreshIndicator = ID("refreshIndicator");
		if (Globals.slideFreshStart) {
			Globals.slideFreshStart = false;
			refreshIndicator.style.transition = 'height .3s ease-in-out';
			if (parseInt(refreshIndicator.style.height) > 80) {
				getFileList();
				refreshIndicator.innerText = '正在刷新';
			} else refreshIndicator.style.height = '0';
		}
	};
	function selectFile(action) {
		switch (action) {
			case 0:
				Array.from(ID("fileList").getElementsByTagName("div")).forEach( element => {
					Globals.selectedFiles.push(element.dataset.name);
				} );
				break;
			case 1:
				Array.from(ID("fileList").getElementsByTagName("div")).forEach( element => {
					if(!Globals.selectedFiles.includes(element.dataset.name)) Globals.selectedFiles.push(element.dataset.name);
					else Globals.selectedFiles = Globals.selectedFiles.filter(item => item != element.dataset.name);
				} );
				break;
			case 2:
				Globals.selectedFiles = [];
				break;
		}
		updateFileListStatus();
	}
	function selectToggle(name) {
		if (!Globals.selectedFiles.includes(name)) Globals.selectedFiles.push(name);
		else Globals.selectedFiles = Globals.selectedFiles.filter(item => item != name);
		updateFileListStatus();
	}
	function handleFilesKeydown() {
		if (event.key.toLowerCase() != "v") event.preventDefault();
		let isSelectFile = true;
		Globals.currentDirInfo.forEach(fileData => {
			if(fileData.name == Globals.selectedFiles[0]){
				if(fileData.isDir) isSelectFile = false;
			}
		});
		switch (event.key.toLowerCase()) {
			case "f2": renameFile(); break;
			case "f4": case "/": editPath(); break;
			case "f5": getFileList(); break;
			case "escape": if (ID("fileMenu").classList.contains("active")) ID("fileMenu").classList.remove("active"); else selectFile(2); break;
			case "delete": deleteFile(); break;
			case "a": if (event.ctrlKey) selectFile(0); break;
			case "c": if (event.ctrlKey) copyFile(false); break;
			case "x": if (event.ctrlKey) copyFile(true); break;
			case "r": if (event.ctrlKey) getFileList(); break;
			case "f": if (event.ctrlKey) createTab("search", Globals.currentDir); break;
			case "d": if (event.altKey && Globals.selectedFiles.length == 1 && isSelectFile) download(); break;
			case "z": if (event.altKey) zipFile(); break;
			case "enter":
				if (Globals.selectedFiles.length == 1) {
					if (event.ctrlKey) visitPage();
					if (event.altKey) fileProps();
					else openFile(Globals.selectedFiles[0], null, !isSelectFile);
				}
				break;
		}
	}
	
	// 文件管理操作
	function closeFileEditing(fileArray = Globals.selectedFiles) {
		for(let file in Globals.fileTabs) {
			fileArray.forEach(fileToClose => {
				if (file.startsWith(fileToClose + "/") || file == fileToClose) closeTab(Globals.fileTabs[file].tabId, true);
			})
		};
	}
	function getRelativePath(a, b) {
		const aArr = a.split("/");
		const bArr = b.split("/");
		let i = 0;
		while (i < aArr.length && i < bArr.length && aArr[i] === bArr[i]) i++;
		let relativePath = "";
		for (let j = i; j < aArr.length - 1; j++) relativePath += "../";
		for (let j = i; j < bArr.length; j++) relativePath += bArr[j] + "/";
		return relativePath.slice(0, -1);
	}
	function copyPath(isRelativeEditor) {
		try {
			if (!isRelativeEditor) {
				navigator.clipboard.writeText(Globals.selectedFiles[0]).then(() => {
					updateMsgBox(createMsgBox(), "复制成功", "已复制此文件相对于 FileAdmin 根目录的路径", "success", 3000, true);
				}, () => {
					updateMsgBox(createMsgBox(), "复制失败", "您的浏览器不允许此应用写入剪切板，请检查权限配置", "error", 3000, true);
				});
			} else {
				let textCopied = false;
				for (let file in Globals.fileTabs) {
					if (Globals.fileTabs[file].tabId == Globals.currentTab) {
						textCopied = true;
						if (Globals.selectedFiles[0] != file) {
							navigator.clipboard.writeText(getRelativePath(file, Globals.selectedFiles[0])).then(() => {
								updateMsgBox(createMsgBox(), "复制成功", "已复制此文件相对于当前编辑器的路径", "success", 3000, true);
							}, () => {
								updateMsgBox(createMsgBox(), "复制失败", "您的浏览器不允许此应用写入剪切板，请检查权限配置", "error", 3000, true);
							});
						} else updateMsgBox(createMsgBox(), "复制失败", "您当前正在编辑此文件", "info", 3000, true);
					}
				}
				if (!textCopied) updateMsgBox(createMsgBox(), "复制失败", "您当前打开的窗口不是编辑器", "info", 3000, true);
			}
		} catch(err) {
			updateMsgBox(createMsgBox(), "复制失败", "由于浏览器限制，请使用 HTTPS 协议访问此应用方可使用此功能", "error", 3000, true);
		}
	}
	function visitPage(file) {
		if (!file) file = Globals.selectedFiles[0];
		window.open("." + file);
	}
	function download(file) {
		if (!file) file = Globals.selectedFiles[0];
		window.open("?run=download&name=" + file);
	}
	function deleteFile() {
		if (!Globals.selectedFiles.length) return;
		let delFiles = Globals.selectedFiles;
		let fileCountUi;
		if (delFiles.length == 1) {
			Globals.currentDirInfo.forEach( fileData => {
				if(fileData.name == delFiles[0]){
					let fileShort = delFiles[0].split("/")[delFiles[0].split("/").length - 1];
					if(fileData.isDir) fileCountUi = `目录「${fileShort}」`;
					else fileCountUi = `文件「${fileShort}」`;
				}
			} );
		} else {
			fileCountUi = `这 ${delFiles.length} 个文件或目录`;
		}
		confirm(`确实要永久删除${fileCountUi}吗？`, "删除文件", () => {
			closeFileEditing();
			let boxId = createMsgBox();
			updateMsgBox(boxId, "正在删除", "正在删除选中的文件，请稍候 ...", "loading");
			request({action: "del", files: delFiles}).then( json => {
				switch (json.code) {
					case 200:
						updateMsgBox(boxId, "删除成功", `已成功删除 ${json.success} 个文件`, "success", 3000, true);
						getFileList();
						break;
					case 1000:
						handleAuthError(boxId);
						break;
					case 1001:
						updateMsgBox(boxId, "删除失败", `已删除 ${json.success} / ${json.total} 个文件，请检查文件访问权限配置`, "error", 3000, true);
						getFileList();
						break;
					default:
						updateMsgBox(boxId, "未知错误", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
						break;
				}
			}).catch( err => { updateMsgBox(boxId, "删除文件失败", "网络错误，请检查网络连接", "error", 3000, true); });
		}, (Globals.currentDir == "" || delFiles.length == Object.keys(Globals.currentDirInfo).length));
	}
	function renameFile() {
		if (Globals.selectedFiles.length != 1) return;
		let file = Globals.selectedFiles[0];
		let oldName = file.split("/")[file.split("/").length - 1];
		let dialogId = prompt("请输入新文件名 ...", "重命名", oldName, newName => {
			closeFileEditing();
			let boxId = createMsgBox();
			if (!newName) return;
			if (oldName == newName) { updateMsgBox(boxId, "重命名失败", "新旧名称重复，无需重命名", "error", 3000, true); return; }
			updateMsgBox(boxId, "正在重命名", "正在重命名文件，请稍候 ...", "loading");
			request({action: "rename", dir: Globals.currentDir, "original": oldName, "new": newName}).then( json => {
				switch (json.code) {
					case 200:
						updateMsgBox(boxId, "重命名成功", "文件已被重命名", "success", 3000, true);
						getFileList();
						break;
					case 1000:
						handleAuthError(boxId);
						break;
					case 1001:
						updateMsgBox(boxId, "重命名失败", "您重命名的文件已被更名或删除", "error", 3000, true);
						break;
					case 1002:
						updateMsgBox(boxId, "重命名失败", "您输入的文件名包含特殊字符", "error", 3000, true);
						break;
					case 1003:
						updateMsgBox(boxId, "重命名失败", "重名文件已存在", "error", 3000, true);
						break;
					case 1004:
						updateMsgBox(boxId, "重命名失败", "请检查文件访问权限配置", "error", 3000, true);
						break;
					default:
						updateMsgBox(boxId, "未知错误", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
						break;
				}
			}).catch( err => { updateMsgBox(boxId, "重命名失败", "网络错误，请检查网络连接", "error", 3000, true); });
		});
		let dirInfo = {};
		Globals.currentDirInfo.forEach(item => {dirInfo[item.name] = item.isDir;});
		if (!dirInfo[oldName] && oldName.lastIndexOf(".") > 0) setTimeout(() => {
			document.querySelector("#FileAdminDialogContainer-" + dialogId + " input").setSelectionRange(0, oldName.lastIndexOf("."));
		}, 5);
	}
	function copyFile(isMove) {
		let originalDir = Globals.currentDir;
		let filesFull = Globals.selectedFiles;
		let files = [];
		filesFull.forEach(file => {files.push(file.split("/")[file.split("/").length - 1])});
		pickDir(isMove?"剪切文件":"复制文件", Globals.currentDir, destinationDir => {
			destinationDir = formatDir(destinationDir);
			let boxId = createMsgBox();
			let opName = isMove?"剪切":"复制";
			if (destinationDir == originalDir) return updateMsgBox(boxId, `${opName}失败`, `您${opName}的目标目录与源目录相同`, "error", 3000, true);
			if (isMove) closeFileEditing(filesFull);
			updateMsgBox(boxId, `正在${opName}`, `正在${opName}文件，请稍候 ...`, "loading");
			request({ action: isMove?"move":"copy" , files: files, original: originalDir, destination: destinationDir }).then(json => {
				switch (json.code) {
					case 200:
						updateMsgBox(boxId, `${opName}成功`, `文件已被${opName}`, "success", 3000, true);
						getFileList(destinationDir);
						break;
					case 1000:
						handleAuthError(boxId);
						break;
					case 1001:
						updateMsgBox(boxId, `${opName}失败`, `您${opName}的文件已被更名或删除`, "error", 3000, true);
						break;
					case 1002:
						updateMsgBox(boxId, `${opName}失败`, `部分文件存在重名`, "error", 3000, true);
						break;
					case 1003:
						updateMsgBox(boxId, `${opName}失败`, `请确认目标文件夹不是源文件夹或其子文件夹，并检查文件访问权限配置`, "error", 3000, true);
						break;
					default:
						updateMsgBox(boxId, "未知错误", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
						break;
				}
			}).catch( err => { updateMsgBox(boxId, `${opName}失败`, "网络错误，请检查网络连接", "error", 3000, true); });
		});
	}
	function zipFile() {
		let files = [];
		Globals.selectedFiles.forEach(file => {files.push(file.split("/")[file.split("/").length - 1])});
		let dir = Globals.currentDir;
		pickDir("选择保存路径", Globals.currentDir, destinationDir => {
			destinationDir = formatDir(destinationDir);
			prompt("在这里输入压缩包名称，可省略后缀名 ...", "压缩文件", "FileAdmin-" + new Date().getTime() + ".zip", name => {
				let boxId = createMsgBox();
				if (!name || name.includes("/")) return;
				if (!name.endsWith(".zip")) name += ".zip";
				name = formatDir(name);
				updateMsgBox(boxId, "正在压缩", "正在压缩文件，请稍候 ...", "loading");
				request({action: "zip" , dir: dir , files: files, name: destinationDir + name}).then(json => {
					switch (json.code) {
						case 200:
							hideMsgBox(boxId);
							confirm("压缩包已成功创建，是否下载到本地？", "压缩成功", () => { download(destinationDir + name); });
							getFileList(destinationDir);
							break;
						case 1000:
							handleAuthError(boxId);
							break;
						case 1001:
							updateMsgBox(boxId, "压缩失败", "压缩包已存在，请更换文件名", "error", 3000, true);
							break;
						case 1002:
							updateMsgBox(boxId, "压缩失败", "您使用的环境可能不支持此功能", "error", 3000, true);
							break;
						default:
							updateMsgBox(boxId, "未知错误", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
							break;
					}
				}).catch( err => { updateMsgBox(boxId, "压缩失败", "网络错误，请检查网络连接", "error", 3000, true); });
			}, "请输入压缩包名称");
		});
	}
	function backupFile(file = Globals.selectedFiles[0]) {
		let boxId = createMsgBox();
		updateMsgBox(boxId, "正在备份", "正在创建副本，请稍候 ...", "loading");
		request({action: "createBackup", file: file}).then(json => {
			switch (json.code) {
				case 200:
					updateMsgBox(boxId, "备份成功", "已在当前目录下创建副本", "success", 3000, true);
					getFileList();
					break;
				case 1000:
					handleAuthError(boxId);
					break;
				case 1001:
					updateMsgBox(boxId, "备份失败", "原文件已被删除", "error", 3000, true);
					getFileList();
					break;
				case 1002:
					updateMsgBox(boxId, "备份失败", "请检查文件访问权限配置", "error", 3000, true);
					break;
				default:
					updateMsgBox(boxId, "未知错误", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
					break;
			}
		}).catch( err => { updateMsgBox(boxId, "备份失败", "网络错误，请检查网络连接", "error", 3000, true); });
	}
	function remoteDownload() {
		confirm("由于 PHP 的限制，FileAdmin 内置的远程下载功能并不稳定，仅可用于下载小型文件。如果您使用的主机或服务器提供了远程下载功能，更推荐使用其内置的远程下载。", "远程下载", () => {
			prompt("支持 HTTP / HTTPS 协议下载 ...", "远程下载", "", url => {
				url = url.trim();
				if (!url) return;
				if (!url.startsWith("http://") && !url.startsWith("https://")) url = "https://" + url;
				let nameUrl = url.split("?")[0].split("#")[0];
				let defaultName = nameUrl.endsWith("/")?"index.html":nameUrl.split("/")[nameUrl.split("/").length-1];
				prompt("本地保存的文件名 ...", "远程下载", defaultName, name => {
					if(!name) name = defaultName;
					name = Globals.currentDir + formatDir(name);
					let boxId = createMsgBox();
					updateMsgBox(boxId, "正在下载", "这可能需要一段时间，请稍候 ...", "loading");
					request({action: "remoteDownload", url: url, name: name}).then(json => {
						switch (json.code) {
							case 200:
								updateMsgBox(boxId, "下载成功", "文件已保存到当前目录下", "success", 3000, true);
								getFileList();
								break;
							case 1000:
								handleAuthError(boxId);
								break;
							case 1001:
								updateMsgBox(boxId, "下载失败", "服务器无法连接到远程地址", "error", 3000, true);
								getFileList();
								break;
							default:
								updateMsgBox(boxId, "未知错误", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
								break;
						}
					}).catch( err => { updateMsgBox(boxId, "下载失败", "可能由于文件体积过大", "error", 3000, true); });
				}, "请输入在服务器本地保存的文件名");
			}, "请输入文件的远程地址，支持 HTTP 和 HTTPS 协议");
		});
	}

	
	// 文件查看
	function getTextFileContent(name, success, error = () => {}, backupMethod = false, boxId = createMsgBox()) {
		if (!backupMethod) {
			updateMsgBox(boxId, "正在获取文件", "正在获取文件内容，请稍候 ...", "loading");
			request({action: "file", file: name}).then( json => {
				switch (json.code) {
					case 200:
						hideMsgBox(boxId);
						success(json.content);
						break;
					case 1000:
						error();
						handleAuthError(boxId);
						break;
					case 1001:
						error();
						getFileList();
						updateMsgBox(boxId, "文件读取失败", "您尝试打开的文件已被删除", "error", 3000, true);
						break;
					case 1002:
						error();
						updateMsgBox(boxId, "文件读取失败", "您尝试打开的文件体积过大，无法使用文本编辑器进行读取", "error", 3000, true);
						break;
					case 1003:
						error();
						updateMsgBox(boxId, "文件读取失败", "文件无法正常读取，请检查文件访问权限配置", "error", 3000, true);
						break;
					default:
						error();
						updateMsgBox(boxId, "文件读取失败", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
						break;
				}
			}).catch(err => {
				if (err.toString().toLowerCase().includes("json")) {
					getTextFileContent(name, success, error, true, boxId);
				} else {
					error();
					updateMsgBox(boxId, "文件读取失败", "出现未知错误，请检查网络连接", "error", 3000, true);
				}
			});
		} else {
			updateMsgBox(boxId, "正在获取文件", "出现未知错误，正在尝试使用备选方案获取文件内容，请稍候 ...", "loading");
			fetch("?run=download&name=" + name)
			.then(res => {return res.text();})
			.then(text => {
				hideMsgBox(boxId);
				success(text);
			})
			.catch(() => {
				error();
				updateMsgBox(boxId, "文件读取失败", "出现未知错误，请检查网络连接", "error", 3000, true);
			});
		}
	}
	let fileHandlers = {
		textEditor: {
			handleFormats: ['txt', 'csv', 'log', 'md', 'json', 'xml', 'yaml', 'html', 'htm', 'tpl', 'css', 'js', 'php', 'java', 'py', 'rb', 'cpp', 'c', 'h', 'sql', 'sh', 'bat', 'vue', 'ini', 'conf', 'yml', 'ts', 'svg'],
			initFunction(name) {
				updateFileTab(null, name);
				getTextFileContent(name, content => {
					createTab("editor", { file: name, content: content});
				}, () => {
					removeFileTab(name);
				});
			},
			uiName: "文本编辑器",
			icon: "F46D",
		},
		mediaPlayer: {
			handleFormats: ['mp3', 'mp4', 'webm', '3gp', 'ogg', 'mov', 'wav', 'm4a', 'flac'],
			initFunction(name) {
				updateFileTab(createTab("mediaPlayer", name), name);
			},
			uiName: "媒体播放器",
			icon: "EB8F",
		},
		imageViewer: {
			handleFormats: ['jpg', 'png', 'jpeg', 'webp', 'gif', 'bmp', 'tiff', 'svg', 'ico'],
			initFunction(name) {
				updateFileTab(createTab("imageViewer", name), name);
			},
			uiName: "图片查看器",
			icon: "EE7D",
		},
		zipExtractor: {
			handleFormats: ['zip'],
			initFunction(name) {
				pickDir("选择解压路径", Globals.currentDir, destinationDir => {
					destinationDir = formatDir(destinationDir);
					prompt("输入压缩包密码，若无密码请留空 ...", "解压文件", "", password => {
						let boxId = createMsgBox();
						updateMsgBox(boxId, "正在解压", "正在解压文件，请稍候 ...", "loading");
						request({action: "unzip", file: name, destination: destinationDir, password: password}).then(json => {
							switch (json.code) {
								case 200:
									updateMsgBox(boxId, "解压成功", "压缩包已解压至目标目录", "success", 3000, true);
									getFileList(destinationDir);
									break;
								case 1000:
									handleAuthError(boxId);
									break;
								case 1001:
									updateMsgBox(boxId, "解压失败", "您输入的压缩包密码有误", "error", 3000, true);
									break;
								case 1002:
									updateMsgBox(boxId, "解压失败", "您使用的环境可能不支持此功能", "error", 3000, true);
									break;
								default:
									updateMsgBox(boxId, "未知错误", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
									break;
							}
						}).catch( err => { updateMsgBox(boxId, "解压失败", "网络错误，请检查网络连接", "error", 3000, true); });
					});
				});
			},
			hidden: true,
		},
		unsupportedArchieve: {
			handleFormats: ['7z', 'rar', 'gz'],
			initFunction(name) {
				alert("FileAdmin 暂不支持处理此 " + getFileExt(name).toUpperCase() + " 格式的压缩文件，请上传 UTF-8 编码的 ZIP 压缩文件以进行在线解压。")
			},
			hidden: true,
		},
		download: {
			handleFormats: [],
			initFunction(name) {
				removeFileTab(name);
				download(name);
			},
			uiName: "下载到本地",
			icon: "EC54",
		},
	};
	let fileIcons = {
		"F33B": ["js"],
		"EC04": ["css"],
		"EBAD": ["html", "htm", "xml", "tpl"],
		"EAE9": ["json", "ts"],
		"F2A6": ["vue"],
		"EC16": ["sql"],
		"F1F8": ["sh", "bat"],
		"F0E6": ["ini", "conf", "yml", "log"],
		"ED0F": ["txt", "csv", "yml", "md", "yaml", "php", "java", "py", "rb", "cpp", "c", "h"],
		"ECF7": ["mp3", "ogg", "wav", "m4a", "flac"],
		"EF7F": ["mp4", "webm", "3gp", "mov"],
		"EE45": ["jpg", "png", "jpeg", "webp", "gif", "bmp", "tiff", "svg", "ico"],
		"EE17": ["bak"],
		"EE51": ["zip", "rar", "7z", "gz"],
	}
	function getIcon(file, isDir) {
		if (isDir) return "&#xED6A;";
		let fileExt = file.split(".")[file.split(".").length-1].toLowerCase();
		for (let icon in fileIcons) {
			if (fileIcons[icon].includes(fileExt)) return `&#x${icon};`;
		}
		return "&#xECEB;";
	}
	function getFileHandler(name) {
		let ext = getFileExt(name);
		let fileSupportedList = [];
		let fullSupportedList = [];
		let firstHandler;
		for (let handlerName in fileHandlers) {
			let supportedFormats = fileHandlers[handlerName].handleFormats;
			if (supportedFormats.includes(ext)) fileSupportedList.push(handlerName);
			else if (!fileHandlers[handlerName].hidden) fullSupportedList.push(handlerName);
		}
		if (fileSupportedList.length == 1) firstHandler = fileSupportedList[0];
		if (getConfig("defaultFileHandler")[ext] && fileHandlers[getConfig("defaultFileHandler")[ext]]) firstHandler = getConfig("defaultFileHandler")[ext];
		return {fileSupportedList, fullSupportedList, firstHandler};
	}
	function showAllHanders() {
		getTabContent(Globals.currentTab, ".openWithContainer").classList.add("full");
	}
	function selectHandler(handler, ele) {
		if (ele.parentElement.querySelector(".active")) ele.parentElement.querySelector(".active").classList.remove("active");
		ele.classList.add("active");
		ele.parentElement.parentElement.dataset.handler = handler;
		getTabContent(Globals.currentTab, ".checkbox").style.opacity = handler == "download" ? 0 : 1;
	}
	function confirmHandler() {
		let file = getTabContent(Globals.currentTab, ".openWithContainer").dataset.file;
		let handler = getTabContent(Globals.currentTab, ".openWithContainer").dataset.handler;
		let isDefault = getTabContent(Globals.currentTab, ".checkbox").checked;
		closeTab(Globals.currentTab);
		openFile(file, handler);
		if (handler != "download") {
			let currentConfig = getConfig("defaultFileHandler");
			if (isDefault) currentConfig[getFileExt(file)] = handler; else delete currentConfig[getFileExt(file)];
			setConfig("defaultFileHandler", currentConfig);
		}
	}
	function openFile(name, forceOpener, isDir){
		if (isDir) return getFileList(name);
		if (!editorData[getConfig("textEditor")].check()) return quickMsgBox("请等待资源文件加载，若此提示频繁出现请在设置中修改「静态资源提供方」");
		if (Globals.fileTabs[name]) return switchTab(Globals.fileTabs[name].tabId);
		if (forceOpener) return fileHandlers[forceOpener].initFunction(name);
		let handlers = getFileHandler(name);
		if (handlers.firstHandler) return fileHandlers[handlers.firstHandler].initFunction(name);
		fileOpenWith(name);
	}
	function fileOpenWith(name = Globals.selectedFiles[0]) {
		if (Globals.fileTabs[name]) return quickMsgBox("当前文件已经打开");
		updateFileTab(createTab("openWith", name), name);
	}
	function updateFileTab(tabId, file) {
		if(!file) for (let filename in Globals.fileTabs) if (Globals.fileTabs[filename].tabId == tabId) file = filename; 
		if(!Globals.fileTabs[file]) Globals.fileTabs[file] = {};
		Globals.fileTabs[file].tabId = tabId;
	}
	function removeFileTab(tabIdOrFile) {
		for(let file in Globals.fileTabs) {
			if(Globals.fileTabs[file].tabId == tabIdOrFile || file == tabIdOrFile) delete Globals.fileTabs[file];
		};
	}
	function newFile(type, createFromDir = Globals.currentDir, callback) {
		let textData = {
			file: ["新建文件", "请输入文件名，支持目录嵌套文件 ..."],
			folder: ["新建文件夹", "请输入目录名，支持创建多级目录 ..."],
		}
		let boxId = createMsgBox();
		prompt(textData[type][1], textData[type][0], null, name => {
			if(!name) return;
			updateMsgBox(boxId, "正在创建", "正在创建文件，请稍候 ...", "loading")
			request({action: "new", type: type, file: createFromDir + formatDir(name)}).then(json => {
				switch (json.code) {
					case 200:
						updateMsgBox(boxId, "文件创建成功", "您的文件创建成功", "success", 2000, true);
						if(type == "file") openFile(createFromDir + formatDir(name), "textEditor");
						getFileList();
						if (callback) callback(createFromDir + formatDir(name));
						break;
					case 1000:
						handleAuthError(boxId);
						break;
					case 1001:
						getFileList();
						updateMsgBox(boxId, "文件创建失败", "已存在重名的文件或目录", "error", 3000, true);
						break;
					case 1002:
						updateMsgBox(boxId, "文件创建失败", "请检查目标目录访问权限", "error", 3000, true);
						break;
					default:
						updateMsgBox(boxId, "文件创建失败", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
						break;
				}
			}).catch( err => { updateMsgBox(boxId, "未知错误", "网络错误，请检查网络连接", "error", 3000, true); });
		});
	}
	function fileProps(file = Globals.selectedFiles[0]) {
		let boxId = createMsgBox();
		updateMsgBox(boxId, "正在请求", "正在请求文件属性，请稍候 ...", "loading")
		request({action: "props", file: file}).then(json => {
			switch (json.code) {
				case 200:
					hideMsgBox(boxId);
					alert(`<div class="filePropTable">
						<div class="row"><span>文件名</span><span>${textEscape(json.props.name)}</span></div>
						<div class="row"><span>绝对路径</span><span>${textEscape(json.props.realpath)}</span></div>
						<div class="row"><span>类型</span><span>${textEscape(json.props.dir?"文件夹":(json.props.format.toUpperCase()+" 文件"))}</span></div>
						<div class="row"><span>大小</span><span>${humanSize(json.props.size)}</span></div>
						<div class="seperator"></div>
						<div class="row"><span>所有者</span><span>${textEscape(json.props.owner)}</span></div>
						<div class="row"><span>权限</span><span>${textEscape(json.props.perm)}</span></div>
						<div class="seperator"></div>
						<div class="row"><span>修改时间</span><span>${humanTime(json.props.mtime * 1000)}</span></div>
						<div class="row"><span>访问时间</span><span>${humanTime(json.props.atime * 1000)}</span></div>
					</div>`, "属性", null, true);
					break;
				case 1000:
					handleAuthError(boxId);
					break;
				case 1001:
					getFileList();
					updateMsgBox(boxId, "请求失败", "您请求的文件已被删除", "error", 3000, true);
					break;
				case 1002:
					updateMsgBox(boxId, "请求失败", "请检查文件访问权限配置", "error", 3000, true);
					break;
				default:
					updateMsgBox(boxId, "请求失败", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
					break;
			}
		}).catch( err => { updateMsgBox(boxId, "未知错误", "网络错误，请检查网络连接", "error", 3000, true); });
	}
	function dirAnalyse(dir = Globals.currentDir) {
		let boxId = createMsgBox();
		updateMsgBox(boxId, "正在分析", "正在分析目录占用，请稍候 ...", "loading")
		request({ action: "analyse", dir: dir}).then(json => {
			switch (json.code) {
				case 200:
					hideMsgBox(boxId);
					createTab("analyse", json.data);
					break;
				case 1000:
					handleAuthError(boxId);
					break;
				case 1001:
					getFileList();
					updateMsgBox(boxId, "分析失败", "您分析的目录已被删除", "error", 3000, true);
					break;
				case 1002:
					updateMsgBox(boxId, "分析失败", "您分析的目录为空目录", "error", 3000, true);
					break;
				default:
					updateMsgBox(boxId, "分析失败", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
					break;
			}
		}).catch( err => { updateMsgBox(boxId, "未知错误", "网络错误，请检查网络连接", "error", 3000, true); });
	}
	
	
	// 文本编辑器
	function initTextEditor() {
		if (Globals.extRuntime.textEditorLoadTries < Globals.extRuntime.blockEditorLoad) return Globals.extRuntime.textEditorLoadTries++;
		editorData[getConfig("textEditor")].init();
	}
	let editorData = {
		monaco: {
			init() {
				if (!window.monaco) loadRemoteScript(
					getStaticUrl("monaco") + "/loader.min.js", 
					() => {
						require.config({
							paths: {"vs": getStaticUrl("monaco")},
							'vs/nls': {availableLanguages: {'*': 'zh-cn'}}
						});
						require(["vs/editor/editor.main"], ()=>{});
					}, 
					() => { updateMsgBox(createMsgBox(), "网络错误", "组件初始化失败，文本编辑器将无法使用，请检查您的网络连接并刷新页面", "error", null, true); }
				);
			},
			check() {
				return !!window.monaco;
			},
			resize() {
				for(let editorTabId in Globals.editors) {
					Globals.editors[editorTabId].layout();
				}
			},
			create(tabId, name, content) {
				// 初始化编辑器
				if (Globals.editors[tabId] && Globals.editors[tabId].dispose) Globals.editors[tabId].dispose();
				let extension = getFileExt(name);
				let supportedLanguages = monaco.languages.getLanguages();
				let language = "plaintext";
				if (extension == "tpl") language = "html";
				supportedLanguages.forEach(lang => {if (lang.extensions && lang.extensions.includes("." + extension)) language = lang.id; });
				let editor = monaco.editor.create( getTabContent(tabId, ".textEditorContainer"), { value: content, language: language, smoothScrolling: true, cursorBlinking: "smooth", cursorSmoothCaretAnimation: true });
				editor.onDidChangeCursorPosition( () => {
					if (this.getValue(tabId) != Globals.editors[tabId].lastSaveContent) { updateTab(tabId, {closeIcon: "&#xEB96;"}); saveLocalWorkspace(); }
					else updateTab(tabId, {closeIcon: "&#xEB99;"});
					getTabContent(tabId, ".lineNumber").innerText = `${Globals.editors[tabId].getPosition().lineNumber}行 ${Globals.editors[tabId].getPosition().column}列`;
				});
				// 初始化编程语言菜单
				let languageSelectHtml = "<optgroup label='常用语言'>";
				let popularLanguages = "纯文本@plaintext|HTML@html|CSS@css|JavaScript@javascript|PHP@php|JSON@json";
				popularLanguages.split("|").forEach(language => {languageSelectHtml += `<option value="${language}">${language.split("@")[0]}</option>`;});
				languageSelectHtml += "</optgroup><optgroup label='所有语言'><option value='自定义@#custom#'>自定义...</option>";
				supportedLanguages.forEach(lang => {
					if (lang.aliases) languageSelectHtml += `<option value="${lang.aliases[0]}@${lang.id}">${lang.aliases[0]}</option>`;
					if (lang.id == language) getTabContent(tabId, ".languageText").innerText = lang.aliases[0];
				});
				languageSelectHtml += "</optgroup>";
				getTabContent(tabId, ".languageSelection").innerHTML = languageSelectHtml;
				getTabContent(tabId, ".languageSelection").value = "";
				getTabContent(tabId, ".language").hidden = false;
				return editor;
			},
			setMode(tabId, language) {
				return monaco.editor.setModelLanguage(Globals.editors[tabId].getModel(), language);
			},
			getValue(tabId) {
				return Globals.editors[tabId].getValue().replace(/\r\n|\r|\n/g, "\n");
			},
			setValue(tabId, value) {
				Globals.editors[tabId].executeEdits("", [{range: Globals.editors[tabId].getModel().getFullModelRange(), text: value}]);
			},
			insertText(tabId, text) {
				let editor = Globals.editors[tabId];
				let position = editor.getPosition();
				let edits = [{
					identifier: { major: 1, minor: 1 },
					range: new monaco.Range(position.lineNumber, position.column, position.lineNumber, position.column),
					text: text,
					forceMoveMarkers: true
				}];
				editor.executeEdits("", edits);
			},
			loadTheme(isDark) {
				if (window.monaco) {
					if(isDark) monaco.editor.setTheme("vs-dark");
					else monaco.editor.setTheme("vs");
				}
			},
			updateConfig() {
				for(let editorTabId in Globals.editors) {
					Globals.editors[editorTabId].updateOptions({
						fontSize: getConfig("editorFontSize"),
						lineHeight: getConfig("editorLineHeight"),
						tabSize: getConfig("editorIndentSize"),
						insertSpaces: getConfig("editorIndentType") == "tab" ? false : true,
						wordWrap: getConfig("editorWrap"),
						mouseWheelScrollSensitivity: 2,
					});
					getTabContent(editorTabId, ".wrapMode").innerText = getConfig("editorWrap") == "off" ? "关" : "开";
					updateModifierStatus(editorTabId);
				}
			},
		},
		ace: {
			init() {
				if (!window.ace) loadRemoteScript(
					getStaticUrl("ace") + "ace.js", 
					() => {
						ace.config.set("basePath", getStaticUrl("ace"));
						loadRemoteScript(getStaticUrl("ace") + "ext-language_tools.js");
					} ,
					() => { updateMsgBox(createMsgBox(), "网络错误", "组件初始化失败，文本编辑器将无法使用，请检查您的网络连接并刷新页面", "error", null, true); }
				);
			},
			check() {
				return !!window.ace;
			},
			resize() {
				for(let editorTabId in Globals.editors) {
					Globals.editors[editorTabId].resize();
				}
			},
			create(tabId, name, content) {
				if(Globals.editors[tabId] && Globals.editors[tabId].destroy) Globals.editors[tabId].destroy();
				let extension = getFileExt(name);
				let language = "text";
				switch (extension) {
					case "html": case "htm": case "tpl": language = "html"; break;
					case "php": language = "php"; break;
					case "css": language = "css"; break;
					case "js": language = "javascript"; break;
					case "php": language = "php"; break;
				}
				let editor = ace.edit(getTabContent(tabId, ".textEditorContainer"));
				editor.setOption("enableLiveAutocompletion", true);
				editor.setOption("scrollPastEnd", 0.5);
				editor.session.setValue(content);
				editor.gotoLine(1);
				editor.setShowPrintMargin(false);
				editor.session.setMode("ace/mode/" + language);
				getTabContent(tabId, ".textEditorContainer").oncontextmenu = e => { e.stopPropagation(); };
				editor.selection.on("changeCursor", () => {
					if (this.getValue(tabId) != Globals.editors[tabId].lastSaveContent) { updateTab(tabId, {closeIcon: "&#xEB96;"}); saveLocalWorkspace(); }
					else updateTab(tabId, {closeIcon: "&#xEB99;"});
					getTabContent(tabId, ".lineNumber").innerText = `${editor.getCursorPosition().row + 1}行 ${editor.getCursorPosition().column + 1}列`;
				});
				return editor;
			},
			setMode(tabId, language) {
				return Globals.editors[tabId].session.setMode("ace/mode/" + language);
			},
			getValue(tabId) {
				return Globals.editors[tabId].getValue().replace(/\r\n|\r|\n/g, "\n");
			},
			setValue(tabId, value) {
				Globals.editors[tabId].setValue(value);
			},
			insertText(tabId, text) {
				Globals.editors[tabId].insert(text);
			},
			loadTheme(isDark) {
				if(isDark) { 
					for(let editorTabId in Globals.editors) {
						Globals.editors[editorTabId].setTheme("ace/theme/monokai");
						getTabContent(editorTabId, ".textEditorContainer").style.setProperty('color-scheme', 'dark');
					}
				} else {
					for(let editorTabId in Globals.editors) {
						Globals.editors[editorTabId].setTheme("ace/theme/chrome");
						getTabContent(editorTabId, ".textEditorContainer").style.setProperty('color-scheme', 'light');
					}
				}
			},
			updateConfig() {
				for(let editorTabId in Globals.editors) {
					getTabContent(editorTabId, ".textEditorContainer").style.fontSize = getConfig("editorFontSize") + "px";
					getTabContent(editorTabId, ".textEditorContainer").style.lineHeight = getConfig("editorLineHeight") + "px";
					Globals.editors[editorTabId].setOptions({
						tabSize: getConfig("editorIndentSize"),
						useSoftTabs: getConfig("editorIndentType") == "tab" ? false : true,
					});
					Globals.editors[editorTabId].getSession().setUseWrapMode(getConfig("editorWrap") == "off" ? false : true);
					Globals.editors[editorTabId].resize();
					getTabContent(editorTabId, ".wrapMode").innerText = getConfig("editorWrap") == "off" ? "关" : "开";
					updateModifierStatus(editorTabId);
				}
			}
		},
		textarea: {
			init() {},
			resize() {},
			create(tabId, name, content) {
				let textarea = document.createElement("textarea");
				textarea.value = content;
				getTabContent(tabId, ".textEditorContainer").appendChild(textarea);
				textarea.addEventListener("keydown", event => {
					if(event.key.toLowerCase() == "tab") {
						event.preventDefault();
						let start = event.target.selectionStart;
						let end = event.target.selectionEnd;
						if (getConfig("editorIndentType") == "space") {
							event.target.value = event.target.value.substring(0, start) + " ".repeat(getConfig("editorIndentSize")) + event.target.value.substring(end);
							event.target.selectionStart = event.target.selectionEnd = start + Number(getConfig("editorIndentSize"));
						} else {
							event.target.value = event.target.value.substring(0, start) + "\t" + event.target.value.substring(end);
							event.target.selectionStart = event.target.selectionEnd = start + 1;
						}
					}
				});
				let updateStatus = () => {
					setTimeout(() => {
						if (editorData.textarea.getValue(tabId) != Globals.editors[tabId].lastSaveContent) { updateTab(tabId, {closeIcon: "&#xEB96;"}); saveLocalWorkspace(); }
						else updateTab(tabId, {closeIcon: "&#xEB99;"});
						let currentLine = textarea.value.substr(0, textarea.selectionStart).split('\n').length;
						let currentColumn = textarea.selectionStart - textarea.value.lastIndexOf('\n', textarea.selectionStart - 1);
						getTabContent(tabId, ".lineNumber").innerText = `${currentLine}行 ${currentColumn}列`;
					}, 1);
				}
				textarea.onkeydown = textarea.onkeyup = textarea.onpointerdown = textarea.onpointerup = textarea.onfocus = textarea.onblur = updateStatus;
				getTabContent(tabId, ".textEditorContainer").oncontextmenu = e => { e.stopPropagation(); };
				return textarea;
			},
			check() {
				return true;
			},
			getValue(tabId) {
				return Globals.editors[tabId].value.replace(/\r\n|\r|\n/g, "\n");
			},
			setValue(tabId, value) {
				Globals.editors[tabId].value = value;
			},
			insertText(tabId, text) {
				event.preventDefault();
				let textarea = getTabContent(tabId, "textarea");
				let start = textarea.selectionStart;
				let end = textarea.selectionEnd;
				if (getConfig("editorIndentType") == "space") {
					textarea.value = textarea.value.substring(0, start) + " ".repeat(getConfig("editorIndentSize")) + textarea.value.substring(end);
					textarea.selectionStart = textarea.selectionEnd = start + Number(getConfig("editorIndentSize"));
				} else {
					textarea.value = textarea.value.substring(0, start) + text + textarea.value.substring(end);
					textarea.selectionStart = textarea.selectionEnd = start + text.length;
				}
			},
			loadTheme() {},
			updateConfig() {
				for(let editorTabId in Globals.editors) {
					Globals.editors[editorTabId].style.fontSize = getConfig("editorFontSize") + "px";
					Globals.editors[editorTabId].style.lineHeight = getConfig("editorLineHeight") + "px";
					Globals.editors[editorTabId].style.tabSize = getConfig("editorIndentSize");
					Globals.editors[editorTabId].style.whiteSpace = getConfig("editorWrap") == "off" ? "nowrap" : "initial";
					getTabContent(editorTabId, ".wrapMode").innerText = getConfig("editorWrap") == "off" ? "关" : "开";
					updateModifierStatus(editorTabId);
				}
			}
		}
	};
	let fileModifier = {};
	function loadEditorTheme() {
		if (getConfig("editorTheme") == "auto") editorData[getConfig("textEditor")].loadTheme(loadUiTheme(true));
		else editorData[getConfig("textEditor")].loadTheme(getConfig("editorTheme") == "dark" ? true : false);
	}
	function toggleEditorWrap() {
		setConfig("editorWrap", getConfig("editorWrap") == "off" ? "on" : "off", true);
	}
	function updateModifierStatus(tabId) {
		setTimeout(() => {
			let suitableEditors = [];
			for(let modifier in fileModifier) {
				if (fileModifier[modifier].formats.includes(getFileExt(Globals.editors[tabId].file))) {
					suitableEditors.push(modifier);
				}
			}
			if (!suitableEditors.length) getTabContent(tabId, ".modifier").hidden = true;
			else {
				getTabContent(tabId, ".modifier").hidden = false;
				let modifierSelectionHtml = `<option value="">未启用</option>`;
				suitableEditors.forEach(modifierName => {
					modifierSelectionHtml += `<option value="${modifierName}">${fileModifier[modifierName].uiName}</option>`;
				});
				getTabContent(tabId, ".modifierSelection").innerHTML = modifierSelectionHtml;
				let currentFileModifier = getConfig("fileModifier")[getFileExt(Globals.editors[tabId].file)];
				getTabContent(tabId, ".modifierSelection").value = currentFileModifier?currentFileModifier:"";
				getTabContent(tabId, ".modifierText").innerText = fileModifier[currentFileModifier]?fileModifier[currentFileModifier].uiName:"未启用";
			}
		}, 10);
	}
	function saveFile(forceDisableModifier) {
		let tabId = Globals.currentTab;
		let boxId = createMsgBox();
		let file = Globals.editors[tabId].file;
		let sendData;
		updateMsgBox(boxId, "正在保存文件", "正在保存文件到服务器 ...", "loading", null, false);
		let content = editorData[getConfig("textEditor")].getValue(tabId);
		if (!fileModifier[getConfig("fileModifier")[getFileExt(file)]] || forceDisableModifier) sendData = {action: "save", file: file, content: content};
		else {
			try{
				sendData = {action: "save", file: file, content: content, modified: fileModifier[getConfig("fileModifier")[getFileExt(file)]].modify(content)};
			} catch (err) {
				hideMsgBox(boxId);
				confirm("代码处理器运行时出现错误。\n错误内容如下，请排查代码中是否存在语法问题等。\n\n" + err + "\n\n是否要保存原文件至服务器？", "保存失败", () => { saveFile(true); })
				return;
			}
		}
		request(sendData).then(json => {
			switch(json.code) {
				case 200:
					updateMsgBox(boxId, "保存成功", "您的更改已同步至服务器", "success", 2000, true);
					Array.from(ID("fileList").getElementsByTagName("div")).forEach(element => {
						if(element.dataset.name == file) element.getElementsByClassName("size")[0].innerText = humanSize(json.size);
					});
					Globals.editors[tabId].lastSaveContent = content;
					if (editorData[getConfig("textEditor")].getValue(tabId) == content) updateTab(tabId, {closeIcon: "&#xEB99;"});
					break;
				case 1000:
					handleAuthError(boxId);
					break;
				case 1001:
					updateMsgBox(boxId, "保存失败", "您保存的文件已被删除", "error", 3000, true);
					break;
				case 1002:
					updateMsgBox(boxId, "保存失败", "文件写入失败，请检查文件访问权限配置", "error", 3000, true);
					break;
				default:
					updateMsgBox(boxId, "保存失败", "出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
					break;
			}
			saveLocalWorkspace();
		}).catch(() => {
			updateMsgBox(boxId, "保存失败", "网络错误，请检查网络连接", "error", 3000, true);
		});
	}
	function refreshEditor() {
		if (Globals.editors[Globals.currentTab].lastSaveContent == editorData[getConfig("textEditor")].getValue(Globals.currentTab)) return refreshEditorConfirm();
		else confirm("您的更改尚未保存，是否从服务器重新获取文件内容？", "刷新编辑器", refreshEditorConfirm);
	}
	function refreshEditorConfirm() {
		let tab = Globals.currentTab;
		let file = Globals.editors[tab].file;
		let fileShort = Globals.editors[tab].fileShort;
		let boxId = createMsgBox();
		getTextFileContent(file, content => {
			getTabContent(tab, ".textEditorContainer").innerHTML = "";
			Globals.editors[tab] = editorData[getConfig("textEditor")].create(tab, file, content);
			Globals.editors[tab].lastSaveContent = content.replace(/\r\n|\r|\n/g, "\n");
			Globals.editors[tab].file = file;
			Globals.editors[tab].fileShort = fileShort;
			editorData[getConfig("textEditor")].resize();
			editorData[getConfig("textEditor")].updateConfig();
			Globals.editors[tab].focus();
		});
	}
	function toggleMobileInput(tabId) {
		let keyboardElement = getTabContent(tabId, ".editorMobileInput");
		if (!keyboardElement.classList.contains("unfold")) keyboardElement.classList.add("unfold");
		else keyboardElement.classList.remove("unfold");
	}
	function keyboardInput(ele) {
		editorData[getConfig("textEditor")].insertText(Globals.currentTab, 
		ele.innerText == "TAB" ? (getConfig("editorIndentType")=="tab" ? "\t" : " ".repeat(Number(getConfig("editorIndentSize")))) : ele.innerText);
	}
	function locateFile(file) {
		if (document.documentElement.classList.contains("fullMode")) toggleFullMode();
		let dir = formatDir(file.substring(0, file.lastIndexOf('/')));
		if (dir == Globals.currentDir) return quickMsgBox("您正在查看文件所在目录");
		openFile(dir, null, true);
	}

	
	// 文件分片上传
	function upload(isFolder) {
		if (isFolder) ID("uploadFolderInput").click();
		else ID("uploadFileInput").click();
	}
	function uploadFiles(fileArray) {
		let fileInput = ID("uploadFileInput");
		let folderInput = ID("uploadFolderInput");
		let tempFileList = [];
		if (folderInput.files.length > 0) for (var i = 0; i < folderInput.files.length; i++) tempFileList.push(folderInput.files[i]);
		else if (fileInput.files.length > 0) for (let i = 0; i < fileInput.files.length; i++) tempFileList.push(fileInput.files[i]);
		else if (fileArray.length > 0) tempFileList = fileArray;
		if (tempFileList.length > 0) {
			let tabId = createTab("upload", tempFileList);
			Globals.upload[tabId] = {
				files: tempFileList,
				totalFiles: tempFileList.length,
				uploadedFiles:0,
			};
			fileInput.value = "";
			folderInput.value = "";
			uploadNextFile(Globals.currentDir, tabId);
		}
	}
	function uploadNextFile(dir, tabId) {
		if (Globals.upload[tabId].uploadedFiles < Globals.upload[tabId].totalFiles) {
			updateTab(tabId, {title: `文件上传 (${Globals.upload[tabId].uploadedFiles}/${Globals.upload[tabId].totalFiles})`});
			let file = Globals.upload[tabId].files[Globals.upload[tabId].uploadedFiles];
			let chunkSize = getConfig("uploadTrunkSize") * 1024 * 1024;
			let totalChunks = Math.ceil(file.size / chunkSize);
			uploadChunk(file, dir, 0, totalChunks, tabId);
		} else {
			updateTab(tabId, {title: "文件上传 (已完成)"});
			delete Globals.upload[tabId];
			getFileList();
		}
	}
	function uploadChunk(file, dir, currentChunk, totalChunks, tabId) {
		let chunkSize = getConfig("uploadTrunkSize") * 1024 * 1024;
		let start = currentChunk * chunkSize;
		let end = Math.min(start + chunkSize, file.size);
		let chunk = file.slice(start, end);
		let formData = new FormData();
		formData.append("file", chunk);
		let uploadDir = dir;
		if (file.webkitRelativePath) uploadDir += formatDir(file.webkitRelativePath.substring(0, file.webkitRelativePath.lastIndexOf("/")) + "/");
		formData.append("data", encodeURIComponent(base64(JSON.stringify({action: "upload", name: file.name, dir: uploadDir, currentChunk: currentChunk, totalChunks: totalChunks}))));
		let xhr = new XMLHttpRequest();
		xhr.open("POST", "?run=backend&stamp=" + new Date().getTime(), true);
		if(!currentChunk) getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}'] .trunkInfo`).innerText = `正在开始`;
		xhr.onload = () => {
			if (xhr.status == 200) {
				try {
					let json = JSON.parse(xhr.responseText);
					switch (json.code) {
						case 200:
							currentChunk++;
							getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}'] .trunkInfo`).innerText = `分片 : ${currentChunk}/${totalChunks}`;
							if (totalChunks) {
								let percent = Math.round( currentChunk / totalChunks * 1000 ) / 10 + "%";
								getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}'] .progressInfo`).innerText = percent;
								getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}'] .progressBar`).style.width = percent;
							}
							if (currentChunk < totalChunks){
								if (json.uploadSuccess) uploadChunk(file, dir, currentChunk, totalChunks, tabId);
								else {
									updateMsgBox(createMsgBox(), "上传失败", "分片文件创建失败，请检查数据目录访问权限", "error", 3000, true);
									getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}'] .trunkInfo`).innerText = `分片创建失败`;
									getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}']`).classList.add("error");
									Globals.upload[tabId].uploadedFiles++;
									uploadNextFile(dir, tabId);
								}
							} else {
								if (json.uploadSuccess && json.fileSuccess) {
									getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}']`).classList.add("success");
								} else {
									updateMsgBox(createMsgBox(), "上传失败", "分片文件合并失败，请检查数据目录和目标目录访问权限", "error", 3000, true);
									getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}'] .trunkInfo`).innerText = `合并失败`;
									getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}']`).classList.add("error");
								}
								Globals.upload[tabId].uploadedFiles++;
								uploadNextFile(dir, tabId);
							}
							break;
						case 1000:
							handleAuthError();
							break;
						case 1001:
							updateMsgBox(createMsgBox(), "上传失败", "服务器已存在此文件", "error", 3000, true);
							getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}'] .trunkInfo`).innerText = `文件已存在`;
							getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}']`).classList.add("error");
							Globals.upload[tabId].uploadedFiles++;
							uploadNextFile(dir, tabId);
							break;
						default:
							updateMsgBox(createMsgBox(), "上传失败", "服务端处理分片时出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
							getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}'] .trunkInfo`).innerText = `上传失败`;
							getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}']`).classList.add("error");
							console.warn("[FileAdminLog]" + xhr.responseText);
							Globals.upload[tabId].uploadedFiles++;
							uploadNextFile(dir, tabId);
							break;
					}
				} catch(error) {
					console.warn(error);
					getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}'] .trunkInfo`).innerText = `上传失败`;
					getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}']`).classList.add("error");
					updateMsgBox(createMsgBox(), "上传失败", "服务端处理分片时出现未知错误，您可以向我们反馈此问题", "error", 3000, true);
					console.warn(error);
					console.warn(xhr.responseText);
					Globals.upload[tabId].uploadedFiles++;
					uploadNextFile(dir, tabId);
				}
			}
		};
		xhr.onerror = () => {
			getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}'] .trunkInfo`).innerText = `网络错误`;
			getTabContent(tabId, `div[data-file-index='${Globals.upload[tabId].uploadedFiles}']`).classList.add("error");
			updateMsgBox(createMsgBox(), "上传失败", "网络错误，请检查网络连接或尝试降低分片大小", "error", 3000, true);
			Globals.upload[tabId].uploadedFiles++;
			uploadNextFile(dir, tabId);
		}
		xhr.send(formData);
	}

/* </script> */<?php break;

// 输出清单文件 =================================================================
case "manifest": header("content-type: text/json"); ?>
	
	{
		"short_name": "FileAdmin",
		"name": "FileAdmin - <?php echo $_SERVER["HTTP_HOST"]; ?>",
		"icons": [ { "src": "https://static.nlrdev.top/sites/fa-inf/icon.png", "type": "image/png", "sizes": "500x500" } ],
		"start_url": "<?php echo $_SERVER['DOCUMENT_URI']?$_SERVER['DOCUMENT_URI']:$_SERVER['PHP_SELF']; ?>",
		"background_color": "white",
		"display": "standalone",
		"theme_color": "white",
		"description": "FileAdmin 文件管理系统"
	}

<?php break;
// 输出网页文件 ==============================================
default:
?>
	<!DOCTYPE html>
	<html>
		<head>
			<link rel="icon" href="https://static.nlrdev.top/sites/fa-inf/icon.png">
			<link rel="stylesheet" href="?run=stylesheet">
			<link rel="manifest" href="?run=manifest">
			<meta charset="utf-8" name="viewport" content="width=device-width,user-scalable=no">
			<meta name="robots" content="noindex,nofollow">
		</head>
		<body>
			<header hidden>
				<div class="branding"><span>File</span><span>Admin</span></div>
				<div id="version">Loading</div>
				<div class="seperator"></div>
				<div class="active" onclick="createTab('donate')"><font>&#xEDF1;</font> 捐助</div>
				<font onclick="createTab('settings')">&#xF0EE;</font>
				<font onclick="createTab('extensions')">&#xEA44;</font>
				<font onclick="logout()">&#xEEDC;</font>
			</header>
			<main id="workspace" hidden onpointerdown="selectFile(2)">
				<div id="files" onkeydown="handleFilesKeydown()" tabindex="0">
					<div id="fileHeader">
						<span>文件管理器</span>
						<div class="seperator"></div>
						<font onclick="createTab('search',Globals.currentDir)">&#xF0D1;</font>
						<font onclick="newFile('file')">&#xECC9;</font>
						<font onclick="newFile('folder')">&#xED5A;</font>
						<font onclick="getFileList()">&#xF33D;</font>
						<font onclick="showFileMenu()" id="pcMenuBtn" onpointerdown="hideFileMenu();event.stopPropagation()">&#xEF32;</font>
						<font onclick="toggleFullMode(true)" id="mobilePageBtn" hidden>&#xEF3D;</font>
					</div>
					<div id="fileAddress"><div class="fileAddressBar"><font onclick="getPrevDir()">&#xEA76;</font><div id="fileAddressText" onclick="editPath()">正在初始化...</div></div></div>
					<div id="refreshIndicator"></div>
					<div id="fileList" class="fileList" onpointerdown="event.stopPropagation()" oncontextmenu="showFileMenu()"></div>
					<div id="fileSelectionBox"></div>
					<div id="fileLoading"></div>
					<div id="fileMenu" onclick="hideFileMenu()" onpointerdown="event.stopPropagation()" onmousewheel="event.stopPropagation()">
						<div id="fileMenuNone">
							<div onclick="selectFile(0)"><font>&#xEB89;</font><span>选择全部</span><small>Ctrl+A</small></div>
							<div onclick="getFileList();this.parentElement.parentElement.classList.remove('active')"><font>&#xF33D;</font><span>刷新</span><small>F5</small></div>
							<div onclick="dirAnalyse()"><font>&#xEFF6;</font><span>目录分析</span></div>
							<div onclick="createTab('search',Globals.currentDir)"><font>&#xF0D1;</font><span>查找文件</span><small>Ctrl+F</small></div>
							<span></span>
							<div onclick="newFile('file')"><font>&#xECC9;</font><span>新建文件</span></div>
							<div onclick="newFile('folder')"><font>&#xED5A;</font><span>新建文件夹</span></div>
							<span></span>
							<div onclick="upload()"><font>&#xED15;</font><span>上传文件</span></div>
							<div onclick="upload(true)" class="mobileDeviceHidden"><font>&#xED82;</font><span>上传文件夹</span></div>
							<div onclick="remoteDownload()"><font>&#xECD9;</font><span>远程下载</span></div>
						</div>
						<div id="fileMenuSingle">
							<div onclick="selectFile(0)"><font>&#xEB89;</font><span>选择全部</span><small>Ctrl+A</small></div>
							<div onclick="selectFile(1)"><font>&#xEC5E;</font><span>反向选择</span></div>
							<div onclick="selectFile(2)"><font>&#xEB7F;</font><span>取消选择</span><small>ESC</small></div>
							<span></span>
							<div onclick="copyFile(true)"><font>&#xF0C1;</font><span>剪切</span><small>Ctrl+X</small></div>
							<div onclick="copyFile(false)"><font>&#xECD5;</font><span>复制</span><small>Ctrl+C</small></div>
							<div onclick="renameFile()"><font>&#xEC86;</font><span>重命名</span><small>F2</small></div>
							<div onclick="zipFile()"><font>&#xEE51;</font><span>压缩文件</span><small>Alt+Z</small></div>
							<span></span>
							<div onclick="copyPath()" class="mobileDeviceHidden"><font>&#xEB91;</font><span>复制相对根目录的路径</span></div>
							<div onclick="copyPath(true)" class="mobileDeviceHidden"><font>&#xEB91;</font><span>复制相对编辑器的路径</span></div>
							<span class="mobileDeviceHidden"></span>
							<div onclick="fileOpenWith()" class="fileOnlyBtn"><font>&#xF2C4;</font><span>打开方式</span></div>
							<div onclick="backupFile()" class="fileOnlyBtn mobileDeviceHidden"><font>&#xF339;</font><span>创建备份</span></div>
							<div onclick="download()" class="fileOnlyBtn"><font>&#xEC54;</font><span>下载到本地</span><small>Alt+D</small></div>
							<div onclick="fileProps()"><font>&#xEE59;</font><span>属性</span><small>Alt+Enter</small></div>
							<div onclick="visitPage()" class="mobileDeviceHidden"><font>&#xF0F4;</font><span>访问页面</span><small>Ctrl+Enter</small></div>
							<div onclick="deleteFile()" class="alert"><font>&#xEC2A;</font><span>删除</span><small>Del</small></div>
						</div>
						<div id="fileMenuMultiple">
							<div onclick="selectFile(0)"><font>&#xEB89;</font><span>选择全部</span><small>Ctrl+A</small></div>
							<div onclick="selectFile(1)"><font>&#xEC5E;</font><span>反向选择</span></div>
							<div onclick="selectFile(2)"><font>&#xEB7F;</font><span>取消选择</span><small>ESC</small></div>
							<span></span>
							<div onclick="copyFile(true)"><font>&#xF0C1;</font><span>剪切</span><small>Ctrl+X</small></div>
							<div onclick="copyFile(false)"><font>&#xECD5;</font><span>复制</span><small>Ctrl+C</small></div>
							<div onclick="zipFile()"><font>&#xEE51;</font><span>压缩文件</span><small>Alt+Z</small></div>
							<span></span>
							<div onclick="deleteFile()" class="alert"><font>&#xEC2A;</font><span>删除</span><small>Del</small></div>
						</div>
					</div>
				</div>
				<div id="resizer"></div>
				<div id="tabs" tabindex="0" onkeydown="handleTabsKeydown()">
					<div id="tabsSwitcher">
						<div class="tab button" onclick="toggleFullMode()" id="fullModeBtn"><font class="icon">&#xEF3D;</font></div>
						<div id="userTabsContainer"></div>
					</div>
					<div id="tabsContent"></div>
				</div>
				<div id="fileUploadTipContainer"><div id="fileUploadTip"><font>&#xF24C;</font> 松手上传到当前目录</div></div>
			</main>
			<main id="login" hidden>
				<div id="loginBox" class="login">
					<font>&#xF10B;</font>
					<form onsubmit="submitLogin()">
						<input id="loginFormUser" autocomplete="username">
						<input type="password" autocomplete="current-password" id="loginFormPassword" placeholder="请输入 FileAdmin 管理密码">
						<button id="loginFormButton">登录</button>
					</form>
				</div>
				<div id="totpBox" class="login" hidden>
					<font>&#xEECF;</font>
					<div id="totpForm">
						<div></div><div></div><div></div><div></div><div></div><div></div>
						<input autocomplete="off" oninput="renderTotp()" type="number" id="totpFormInput">
					</div>
					<p><font>&#xF108;</font> 请输入二步验证码以继续</p>
				</div>
			</main>
			<div id="msgBoxContainer"></div>
			<input type="file" id="uploadFileInput" multiple onchange="uploadFiles()" hidden>
			<input type="file" id="uploadFolderInput" webkitdirectory directory multiple onchange="uploadFiles()" hidden>
		</body>
		<script src="?run=javascript"></script>
	</html>
<?php break; } ?>