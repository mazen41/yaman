with open(r'D:\yaman\databasefile\u724930382_yamanstore.sql', encoding='utf-8', errors='replace') as f:
    for i, line in enumerate(f, 1):
        if 95902 <= i <= 95960:
            print(f"{i}: {line}", end='')
