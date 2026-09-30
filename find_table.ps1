$f = 'D:\yaman\databasefile\u724930382_yamanstore.sql'
$result = Select-String $f -Pattern 'CREATE TABLE `purchase_baskets`'
Write-Output $result.LineNumber
