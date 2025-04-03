{pkgs}: {
  deps = [
    pkgs.php
    pkgs.firefox
    pkgs.chromium
    pkgs.geckodriver
    pkgs.glibcLocales
    pkgs.postgresql
    pkgs.openssl
  ];
}
