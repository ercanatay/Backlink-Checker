# PHP SEO & Backlink Checker Tool

Bu araç, belirtilen URL listesini analiz ederek çeşitli SEO metriklerini ve geri bağlantı (backlink) bilgilerini kontrol eden basit bir PHP betiğidir. Sonuçları web arayüzünde görüntüler ve CSV, TXT, XLS formatlarında indirme seçeneği sunar.

This tool is a simple PHP script that analyzes a given list of URLs to check various SEO metrics and backlink information. It displays the results in a web interface and offers download options in CSV, TXT, and XLS formats.

---

## 🇹🇷 Türkçe Açıklama

### Özellikler

* **Noindex Kontrolü:** Her bir URL'nin kaynak kodunda `noindex` meta etiketinin olup olmadığını kontrol eder.
* **Backlink Kontrolü:** Her bir URL'de, forma girilen hedef "Root Domain"e işaret eden bir geri bağlantı (backlink) olup olmadığını kontrol eder.
* **Link Tipi ve Anchor Text:** Eğer bir backlink bulunursa, linkin `dofollow` mu yoksa `nofollow` mu olduğunu ve linkin anchor text'ini (bağlantı metni) belirler.
* **Moz Entegrasyonu:** Her bir URL için Moz API'sini kullanarak Sayfa Otoritesi (PA) ve Alan Otoritesi (DA) değerlerini çeker.
* **Domain Bilgisi:** Analiz edilen her URL'nin alan adını ayrı bir sütunda gösterir.
* **Sıralama:** Sonuçları Alan Otoritesi (DA) değerine göre büyükten küçüğe doğru sıralar.
* **Stil:** Link tiplerini (dofollow/nofollow) ve DA değerlerini renklendirme ve kalınlaştırma ile vurgular.
* **İndirme Seçenekleri:** Analiz sonuçlarını CSV, TXT veya basit bir Excel (XLS) formatında indirmenize olanak tanır.
* **Arayüz:** Basit bir HTML formu ve UIkit ile stillendirilmiş sonuç tablosu sunar.

### Nasıl Çalışır?

1.  Kullanıcı, hedef "Root Domain"i ve kontrol edilecek URL listesini web formuna girer.
2.  Betik, girilen her bir geçerli URL için aşağıdaki işlemleri yapar:
    * PHP cURL kullanarak URL'nin HTML içeriğini çeker.
    * DOMDocument ve DOMXPath kullanarak HTML içeriğini ayrıştırır (parse eder).
    * `noindex` meta etiketini arar.
    * Hedef "Root Domain"e giden `<a>` etiketlerini arar. Bulunursa, anchor text'i ve `rel="nofollow"` özelliğini kontrol ederek link tipini belirler.
    * URL'den alan adını çıkarır.
    * Eğer sayfa başarıyla çekildiyse, o URL için Moz API'sine ayrı bir istek göndererek PA ve DA değerlerini alır (HTTP Basic Authentication kullanarak).
    * Tüm URL'ler işlendikten sonra, sonuçları DA değerine göre büyükten küçüğe sıralar.
3.  İşlenen ve sıralanan sonuçlar, stil uygulanmış bir HTML tablosunda gösterilir.
4.  Sonuçları farklı formatlarda indirmek için butonlar sunulur (PHP session kullanarak).

### Kurulum

1.  **Gereksinimler:** PHP (cURL eklentisi aktif) kurulu bir web sunucusu.
2.  Bu PHP dosyasını (`index.php` veya istediğiniz bir isimle) web sunucunuzda erişilebilir bir dizine yükleyin.
3.  **Moz API Anahtarları:** Geçerli bir Moz API aboneliğinizin ve API anahtarlarınızın (Access ID ve Secret Key) olması gereklidir.
4.  **Kodu Düzenleyin:** PHP dosyasını bir metin düzenleyici ile açın ve aşağıdaki değişkenleri **kendi Moz API bilgilerinizle** güncelleyin:
    ```php
    $mozAccessId = 'mozscape-YOUR-ACCESS-ID'; // Kendi Access ID'nizi buraya girin
    $mozSecretKey = 'YOUR-SECRET-KEY'; // Kendi Secret Key'inizi buraya girin
    ```
    **⚠️ Güvenlik Uyarısı:** API anahtarlarını doğrudan koda gömmek production ortamları için güvenli değildir. Daha güvenli yöntemler için ortam değişkenlerini (environment variables) veya ayrı bir konfigürasyon dosyası kullanmayı edin.
5.  Dosyayı kaydedin.

### Kullanım

1.  PHP dosyasını yüklediğiniz adresi web tarayıcınızda açın.
2.  **Root Domain:** Geri bağlantıları hangi alan adına *doğru* aradığınızı girin (örn: `sizinwebsiteniz.com`). Bu alan adına verilen linkler kontrol edilecektir.
3.  **URLs to Check:** Hangi URL'lerde geri bağlantı aramak istediğinizi listeleyin (her satıra bir URL). Bu URL'ler, yukarıda girdiğiniz "Root Domain"e link veriyor mu diye kontrol edilecektir.
4.  "Start Checking" butonuna tıklayın.
5.  İşlem tamamlandığında sonuçlar aşağıdaki tabloda görünecektir.
6.  Sonuçları indirmek için ilgili butonları (CSV, Excel, TXT) kullanın.

### Bağımlılıklar

* **UIkit CSS/JS:** Stil ve bazı arayüz bileşenleri için UIkit kullanılır (CDN üzerinden yüklenir). İnternet bağlantısı gerektirir.

---

## 🇺🇸 English Description

### Features

* **Noindex Check:** Checks if a `noindex` meta tag exists in the source code of each URL.
* **Backlink Check:** Checks if each URL contains a backlink pointing to the target "Root Domain" entered in the form.
* **Link Type & Anchor Text:** If a backlink is found, determines if the link is `dofollow` or `nofollow` and extracts its anchor text.
* **Moz Integration:** Fetches Page Authority (PA) and Domain Authority (DA) values for each URL using the Moz API.
* **Domain Info:** Displays the domain name of the analyzed URL in a separate column.
* **Sorting:** Sorts the results in descending order based on Domain Authority (DA).
* **Styling:** Highlights link types (dofollow/nofollow) and DA values with colors and bolding.
* **Download Options:** Allows downloading the analysis results in CSV, TXT, or a simple Excel (XLS) format.
* **Interface:** Provides a simple HTML form and a results table styled with UIkit.

### How it Works

1.  The user enters the target "Root Domain" and a list of URLs to check via the web form.
2.  The script processes each valid input URL:
    * Fetches the HTML content of the URL using PHP cURL.
    * Parses the HTML content using DOMDocument and DOMXPath.
    * Searches for the `noindex` meta tag.
    * Searches for `<a>` tags linking to the target "Root Domain". If found, extracts anchor text and checks the `rel="nofollow"` attribute to determine the link type.
    * Extracts the domain name from the URL.
    * If the page was fetched successfully, sends a separate request to the Moz API for that single URL to retrieve PA and DA values (using HTTP Basic Authentication).
    * After processing all URLs, sorts the results by DA in descending order.
3.  The processed and sorted results are displayed in a styled HTML table.
4.  Buttons are provided to download the results in different formats (using PHP sessions).

### Setup

1.  **Requirements:** A web server with PHP installed and the cURL extension enabled.
2.  Upload this PHP file (e.g., `index.php`) to an accessible directory on your web server.
3.  **Moz API Keys:** You need a valid Moz API subscription and your API credentials (Access ID and Secret Key).
4.  **Edit the Code:** Open the PHP file in a text editor and update the following variables with **your Moz API credentials**:
    ```php
    $mozAccessId = 'mozscape-YOUR-ACCESS-ID'; // Enter your Access ID here
    $mozSecretKey = 'YOUR-SECRET-KEY'; // Enter your Secret Key here
    ```
    **⚠️ Security Warning:** Hardcoding API keys directly into the code is not recommended for production environments. Consider using environment variables or a separate configuration file for better security.
5.  Save the file.

### Usage

1.  Open the URL of the uploaded PHP file in your web browser.
2.  **Root Domain:** Enter the domain you want to check *for* backlinks pointing *to* it (e.g., `yourwebsite.com`).
3.  **URLs to Check:** List the URLs where you want to search *for* backlinks (one URL per line). These URLs will be checked to see if they link to the "Root Domain" entered above.
4.  Click the "Start Checking" button.
5.  Once the process is complete, the results will be displayed in the table below.
6.  Use the download buttons (CSV, Excel, TXT) to export the results.

### Dependencies

* **UIkit CSS/JS:** Used for styling and some interface components (loaded via CDN). Requires an internet connection.

---

## Kaynak ve Geliştirici / Source and Developer

Bu araç, Ercan Atay tarafından geliştirilen orijinal [Backlink-Checker](https://github.com/ercanatay/Backlink-Checker) projesinden ilham alınarak ve üzerine Moz API entegrasyonu gibi ek özellikler eklenerek geliştirilmiştir.

This tool was developed with inspiration from the original [Backlink-Checker](https://github.com/ercanatay/Backlink-Checker) project by Ercan Atay, with additional features such as Moz API integration.

**Orijinal Geliştirici / Original Developer:** Ercan ATAY ([https://www.ercanatay.com](https://www.ercanatay.com))

---

## License

(Projeniz için bir lisans eklemeyi düşünün, örneğin MIT Lisansı)
Bu kodu kaynak göstererek kullanabilirsiniz.

(Consider adding a license for your project, e.g., MIT License)
You can use this code with attribution.

