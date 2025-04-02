{pkgs}: {
  deps = [
    pkgs.firefox
    pkgs.chromium
    pkgs.geckodriver
    pkgs.glibcLocales
    pkgs.postgresql
    pkgs.openssl
  ];
}
