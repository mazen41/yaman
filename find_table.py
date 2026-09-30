import subprocess
result = subprocess.run(['findstr', '/n', 'purchase_baskets', r'D:\yaman\databasefile\u724930382_yamanstore.sql'], capture_output=True, text=True)
lines = result.stdout.split('\n')
for l in lines[:5]:
    print(l[:120])
