<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/partials/layout.php';
require_once __DIR__ . '/lib/partials/citation.php';

$copyrightYear = (int) date('Y');

$cfg = pinchard_config();
$cloudberryPhotos = getObjectList($cfg['s3_bucket_thumbnails']);
usort($cloudberryPhotos, fn ($a, $b) => $a['date'] <=> $b['date']);
$cloudberryArchiveSpan = pinchard_cloudberry_archive_span($cloudberryPhotos);
$cloudberryInfoDescription = pinchard_cloudberry_info_description($cloudberryArchiveSpan);

pinchard_layout_head('Cloudberry — About', [
    'description' => $cloudberryInfoDescription,
    'body_class' => 'info-page',
    'json_ld' => [
        [
            '@type' => 'AboutPage',
            'name' => 'About Cloudberry',
            'description' => $cloudberryInfoDescription,
            'url' => pinchard_absolute_url('/info.php'),
            'mainEntity' => [
                '@type' => 'CreativeWork',
                'name' => 'Cloudberry',
                'description' => pinchard_cloudberry_site_description(),
                'creator' => [
                    ['@type' => 'Person', 'name' => 'Adam Simms'],
                    ['@type' => 'Person', 'name' => 'Angela Gabereaux'],
                ],
            ],
        ],
    ],
]);

pinchard_layout_nav(['active' => 'info']);
?>
    <h1 class="visually-hidden">About Cloudberry</h1>
    <div class="info-hero">
        <img src="images/info/pano.jpg" class="img-fluid info_img" alt="View from Precious Memories cabin on Pinchard's Island" fetchpriority="high" decoding="async">
    </div>

    <div class="info-layout">
        <aside class="info-toc" aria-label="Table of contents">
            <nav>
                <a href="#about" class="is-active" aria-current="true">About</a>
                <a href="#how">How</a>
                <a href="#who">Who</a>
                <a href="#more">More</a>
                <a href="#contact">Contact</a>
            </nav>
        </aside>

        <main class="info-main">
    <div class="how_section" id="about">
        <div class="container">
            <div class="row justify-content-center"><div class="col-12 col-md-10 col-lg-8">
                <h2>About</h2>

                <p><strong>Cloudberry</strong> was an off-the-grid, solar-powered, long-term photography project. Using a GoPro, a Raspberry Pi, and a USB cellular modem, the system we designed took one photograph per hour between 8 AM and 8 PM each day and uploaded the images via a cellular network to this website.</p>

                <p>The photographs depict a view of Pinchard's Island from a small, family-owned cabin named "Precious Memories." The island, only accessible by boat for a few weeks of the year, is home to a few cabins that resettled residents use while picking bake apples (the local term for cloudberries) during the summer months.</p>

                <img src="images/info/precious-moments.jpg" class="img-fluid info_img" alt="Precious Memories cabin" width="1500" height="1000" loading="lazy" decoding="async">

                <p>The view was static—in the sense that the camera always captured the same frame; however, the lighting of the frame could vary drastically from one image to another. They extended the habit of glancing out the cabin window at the surrounding landscape.</p>

                <?php if ($cloudberryArchiveSpan !== null): ?>
                <p>Cloudberry operated from <?= pinchard_h($cloudberryArchiveSpan['start']) ?> through <?= pinchard_h($cloudberryArchiveSpan['end']) ?>. The camera system eventually failed—likely from cold, weathering, too little sun, or some combination. This website is the archive and documentation of what it captured.</p>
                <?php endif; ?>

                <h2>Okay, but why Pinchard's Island?</h2>

                <p><a href="http://adamsim.ms/" target="_blank" rel="noopener noreferrer">Adam</a> has been photographing <a href="http://adamsim.ms/pinchards-island/" target="_blank" rel="noopener noreferrer">Pinchard's Island</a> and its previous residents for several years. The harsh weather conditions and the extreme remoteness of the island made it difficult to access the island year round and take images over long periods of time. Cloudberry grew from the desire to be able to photograph the island throughout the year from anywhere via the internet.</p>

                <img src="images/info/pinchards-island-sisters.jpg" class="img-fluid info_img" alt="Pinchard's Island Sisters" width="1000" height="667" loading="lazy" decoding="async">

                <p>Shortly after Newfoundland joined Canada as its 10th province, Pinchard's Island was <a href="http://adamsim.ms/resettlement/" target="_blank" rel="noopener noreferrer">resettled</a> in an attempt to modernize the province. Adam has been documenting the return of his grandmother, along with her brothers and sisters, to this island each summer in an attempt to write the future of resettlement by reviving traditions and creating new ones.</p>

                <h2>Where is Pinchard's Island?</h2>
                <p>Pinchard's Island is situated at the northern edge of Bonavista Bay, Newfoundland, Canada. It was one of the first settled sites in Bonavista Bay but is no longer inhabited.</p>

                <div class="info-map">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d20855.569930035457!2d-53.48462861918841!3d49.20157937004537!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4b75e272b66fd9bf%3A0xe011372b0d414175!2sPinchards+Island%2C+New-Wes-Valley%2C+NL+A0G+3L0%2C+Canada!5e0!3m2!1sen!2sgr!4v1503767433902" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map of Pinchard's Island"></iframe>
                </div>
            </div></div>
        </div>
    </div>

    <div class="how_section" id="how">
        <div class="container">
            <div class="row justify-content-center"><div class="col-12 col-md-10 col-lg-8">
                <h2>How?</h2>

                <p>Adam and Angela met early May 2017 to briefly discuss the possibility of collaborating together. The idea was loose, but the goal was to take photos of the island remotely, upload the images via the cellular network and access them from anywhere. We both shared connections to Newfoundland, and a passion for art and technology, so we set out to see what was possible.</p>

                <p>Our initial research showed that there were a lot of possibilities as to how we could approach the project, but it was clear from the beginning that every decision would result in many constraints that would affect every decision we made. Power, temperature, weather, sunlight, data limits, storage, remoteness, were all components that constantly determined decision making and the methods in which we worked.</p>

                <p>We used <a href="http://www.trello.com/" target="_blank" rel="noopener noreferrer">Trello</a> to plan every aspect of the project, communicate, and document our research:</p>

                <a href="https://trello.com/b/eYzSO4qQ/shutter-island" target="_blank" rel="noopener noreferrer"><img src="images/info/trello.jpg" class="img-fluid info_img" alt="Trello project board" width="1408" height="706" loading="lazy" decoding="async"></a>

                <p>Designing a system that worked was only the start of the project. Every slight adjustment that we made to the system, such as moving from electricity to solar power, putting the USB cellular modem in a case or using different USB cables introduced new problems that we had to constantly monitor and resolve. Once we felt confident in our system, we had to be realistic that once the system was installed on the island, we would not be able to physically be there to troubleshoot any problem that might arise. This forced us to evaluate the entire solution and implement different components to help reduce the risk factor of the project.</p>

                <img src="images/info/notebook.jpg" class="img-fluid info_img" alt="Project notebook" width="1400" height="933" loading="lazy" decoding="async">

                <p>The entire system took us approximately 3 months to build. This includes the initial idea, research, system design, installation, and final production code. Below is a system diagram and an outline of all the hardware and software used to create Cloudberry. The Raspberry Pi field software is open source as <a href="https://github.com/adamsimms/cloudberry" target="_blank" rel="noopener noreferrer">Cloudberry</a>.</p>

                <h2>The Cloudberry System</h2>

                <a href="https://www.figma.com/file/GvUAbr6vcpJ2Ruk1T1q4e20Z/Shutter-Island?node-id=35%3A116" target="_blank" rel="noopener noreferrer"><img src="images/info/cloudberry-system.jpg" class="img-fluid info_img info-system-diagram" alt="Cloudberry system diagram" width="1600" height="957" loading="lazy" decoding="async"></a>

                <h2>Hardware</h2>

                <div class="hardware-accordion">
                    <details class="hardware-details">
                        <summary>GoPro HERO4 Black with 16gb micro SD Card</summary>
                        <div class="hardware-details-body">
                                <p>We initially wanted to use the GoPro HERO5, but the Cam Do enclosure did not support the GoPro HERO5 at the time. Creating a DIY weatherproof enclosure didn't add any benefit since the image quality between GoPro HERO4 and GoPro HERO5 is the same; therefore, we made the decision to go with the GoPro HERO4.</p>
                                <p><a href="https://www.amazon.ca/GoPro-MAIN-91068-HERO4-BLACK/dp/B00NIYNUF2" target="_blank" rel="noopener noreferrer">GoPro HERO4 Black on Amazon</a></p>
                        </div>
                    </details>
                    <details class="hardware-details">
                        <summary>Cam Do Blink Interval Timer</summary>
                        <div class="hardware-details-body">
                                <p>The interval timer turned on the GoPro every hour between 8 AM and 8 PM. The GoPro was modified with the <a href="https://cam-do.com/products/csi-pro-firmware" target="_blank" rel="noopener noreferrer">Pro-csiController</a> firmware from Cam Do and ran a custom <code>autoexec</code> script to take a photo, turn on the GoPro WiFi, and then put the camera in standby mode to conserve power.</p>
                                <p><a href="https://cam-do.com/collections/schedulers/products/blink-gopro-time-lapse-controller" target="_blank" rel="noopener noreferrer">Cam Do Blink Interval Timer</a></p>
                        </div>
                    </details>
                    <details class="hardware-details">
                        <summary>Raspberry Pi 3 Model B</summary>
                        <div class="hardware-details-body">
                                <p>Standard Raspberry Pi with a <a href="https://www.adafruit.com/product/1583" target="_blank" rel="noopener noreferrer">16GB Noobs SD Card</a> running <a href="https://www.raspberrypi.org/downloads/raspbian/" target="_blank" rel="noopener noreferrer">Raspbian OS</a>.</p>
                        </div>
                    </details>
                    <details class="hardware-details">
                        <summary>WittyPi 2</summary>
                        <div class="hardware-details-body">
                                <p>A real time clock (RTC) that turned on the Raspberry Pi at night for 30 minutes. The Raspberry Pi ran a Python script that:</p>
                                <ol>
                                    <li>Woke up the GoPro via WiFi.</li>
                                    <li>Downloaded and deleted the latest images from the GoPro.</li>
                                    <li>Uploaded the images to cloud object storage and deleted the images from the Raspberry Pi.</li>
                                    <li>Put the GoPro into standby mode and shut down the Raspberry Pi to conserve power.</li>
                                </ol>
                                <p><a href="http://www.uugear.com/product/wittypi2/" target="_blank" rel="noopener noreferrer">WittyPi 2</a></p>
                        </div>
                    </details>
                    <details class="hardware-details">
                        <summary>Arduino Pro Mini + Current Sensor</summary>
                        <div class="hardware-details-body">
                                <p>We added a power-monitoring meter to avoid power issues that might corrupt the SD card if the Raspberry Pi shut down unexpectedly. In the event that the power supply was low, the Arduino triggered the WittyPi 2 to shut down the Raspberry Pi until there was sufficient battery to power the device.</p>
                        </div>
                    </details>
                    <details class="hardware-details">
                        <summary>WatchDog from Switch Doc</summary>
                        <div class="hardware-details-body">
                                <p>Since the Raspberry Pi was managed remotely, the WatchDog monitored the health of the Raspberry Pi and automatically restarted the system in the event of a malfunction.</p>
                        </div>
                    </details>
                    <details class="hardware-details">
                        <summary>Huawei LTE E8372 USB Cellular Modem</summary>
                        <div class="hardware-details-body">
                                <p>The USB cellular modem was connected to the Raspberry Pi USB port with a SIM card and mobile internet plan from Bell Canada. At ±6 MB per photo and 13 photos per day, the system used roughly 2.3 GB per month.</p>
                        </div>
                    </details>
                    <details class="hardware-details">
                        <summary>GoPro Weatherproof Enclosure</summary>
                        <div class="hardware-details-body">
                                <p>Housed the GoPro. We made several modifications to the enclosure including silicone sealing, a drilled power port, and removing the air pressure filter to reduce condensation on the lens filter.</p>
                        </div>
                    </details>
                    <details class="hardware-details">
                        <summary>130 Watt Solar Power System</summary>
                        <div class="hardware-details-body">
                                <p>We worked with Gerry of <a href="http://www.nfenergies.com/" target="_blank" rel="noopener noreferrer">Newfound Energies</a> in St. John's Newfoundland to design a solar panel system for intermittent power in harsh winter conditions.</p>
                                <ul>
                                    <li>130-watt solar panel pointed at ±20 degrees towards the path of the sun during the winter.</li>
                                    <li>40 amp solar charge controller</li>
                                    <li>1500-watt inverter</li>
                                    <li>Four 6 volt, 385 amp hour deep cycle batteries</li>
                                </ul>
                        </div>
                    </details>
                    <details class="hardware-details">
                        <summary>Remote access &amp; cloud storage</summary>
                        <div class="hardware-details-body">
                                <p><a href="http://www.dataplicity.com/" target="_blank" rel="noopener noreferrer">Dataplicity</a> allowed remote CLI access to the Raspberry Pi.</p>
                                <p>Cloud object storage held the photographs during capture; a CDN delivered them to visitors. Gallery media now lives on Cloudflare R2 (<code>cloudberry-images.adamsimms.xyz</code> / <code>cloudberry-thumbs.adamsimms.xyz</code>).</p>
                                <p><a href="https://github.com/KonradIT/goprowifihack" target="_blank" rel="noopener noreferrer">GoPro WiFi Hack</a> from KonradIT enabled remote camera control.</p>
                        </div>
                    </details>
                </div>

                <h2>Installation</h2>
                <p>During the second week of August, we embarked on our journey to install Cloudberry. The first task was to bring all of the solar power components to the island, which was a task that required four people to load the housing unit, batteries, and solar panel. It took approximately two days for Roger and Adam to install the entire system with constant readjustments.</p>

                <img src="images/info/solar-install.jpg" class="img-fluid info_img" alt="Solar power installation" width="1000" height="1000" loading="lazy" decoding="async">

                <p>The second step was to choose the frame of the photograph and install the Cam Do enclosure. Pointing the camera towards the north avoided sun blasting and offers views of both the landscape and the ocean.</p>

                <img src="images/info/cam-do.jpg" class="img-fluid info_img" alt="Cam Do enclosure" width="1400" height="934" loading="lazy" decoding="async">

                <p>Once the entire system was in place, we monitored everything for a full day cycle before locking up all of the cases and leaving the island.</p>

                <img src="images/info/pi.jpg" class="img-fluid info_img" alt="Raspberry Pi assembly" width="1400" height="933" loading="lazy" decoding="async">
            </div></div>
        </div>
    </div>

    <div class="who_section" id="who">
        <div class="container">
            <div class="row justify-content-center"><div class="col-12 col-md-10 col-lg-8">
                <img src="images/info/yay.jpg" class="img-fluid info_img" alt="Cloudberry creators" width="3000" height="2250" loading="lazy" decoding="async">
                <h2>Who made Cloudberry?</h2>
                <ul class="people-list">
                    <li class="people-list-item">
                        <img class="people-list-photo" src="/images/people/adam-simms.jpg" alt="" width="72" height="72" loading="lazy" decoding="async">
                        <div class="people-list-body">
                            <div class="people-list-name">Adam Simms</div>
                            <div class="people-list-role">Photographer, designer, developer</div>
                            <a href="http://adamsim.ms/" target="_blank" rel="noopener noreferrer" class="link">www</a>
                        </div>
                    </li>
                    <li class="people-list-item">
                        <img class="people-list-photo" src="/images/people/angela-gabereaux.jpg" alt="" width="72" height="72" loading="lazy" decoding="async">
                        <div class="people-list-body">
                            <div class="people-list-name">Angela Gabereaux</div>
                            <div class="people-list-role">Software developer, systems engineer</div>
                            <a href="https://www.ada-x.org/en/participants/angela-gabereau-2/" target="_blank" rel="noopener noreferrer" class="link">www</a>
                        </div>
                    </li>
                    <li class="people-list-item">
                        <img class="people-list-photo" src="/images/people/roger-knight.jpg" alt="" width="72" height="72" loading="lazy" decoding="async">
                        <div class="people-list-body">
                            <div class="people-list-name">Roger Knight</div>
                            <div class="people-list-role">Equipment operator, carpenter</div>
                        </div>
                    </li>
                </ul>
            </div></div>
        </div>
    </div>

    <div class="how_section" id="more">
        <div class="container">
            <div class="row justify-content-center"><div class="col-12 col-md-10 col-lg-8">
                <h2>Citations</h2>
                <p>Researchers and publications are welcome to use Cloudberry photographs with attribution. The archive is complete and no longer receiving new images.</p>
                <p>The suggested format below follows the <strong>Chicago Manual of Style, Author-Date</strong> system, adapted for a born-digital photograph archive (similar to citing a website or online collection).</p>

                <h3>Citing the entire archive</h3>
                <p>Use this when referring to the project or website as a whole.</p>
                <?php pinchard_citation_block([
                    'text' => pinchard_citation_archive(),
                    'label' => 'Archive citation',
                    'hint' => 'The access date reflects the day you loaded this page.',
                    'class' => 'citation-block--spaced-below',
                ]); ?>

                <h3>Citing a specific photograph</h3>
                <p>Open the image on the site, expand the details panel, and note the <strong>date and time</strong>, <strong>photo number</strong> (shown as the large title), and <strong>filename</strong> from the page URL (<code>?filename=…</code>). Substitute those values into the template below.</p>
                <?php pinchard_citation_block([
                    'text' => pinchard_citation_photo_template(),
                    'label' => 'Individual photograph template',
                    'hint' => 'Replace bracketed fields with values from the photograph you are citing. The access date reflects the day you loaded this page.',
                ]); ?>

                <h2>Keyboard shortcuts</h2>
                <ul class="keyboard-shortcuts">
                    <li>
                        <span class="keyboard-shortcuts-keys"><kbd>←</kbd> <kbd>→</kbd></span>
                        <span class="keyboard-shortcuts-action">Previous / next photograph</span>
                    </li>
                    <li>
                        <span class="keyboard-shortcuts-keys"><kbd>↑</kbd> <kbd>↓</kbd></span>
                        <span class="keyboard-shortcuts-action">Open / close details</span>
                    </li>
                    <li>
                        <span class="keyboard-shortcuts-keys"><kbd>←</kbd> <kbd>→</kbd> <kbd>↑</kbd> <kbd>↓</kbd></span>
                        <span class="keyboard-shortcuts-action">Move in gallery</span>
                    </li>
                    <li>
                        <span class="keyboard-shortcuts-keys"><kbd>Space</kbd></span>
                        <span class="keyboard-shortcuts-action">Pause / resume</span>
                    </li>
                    <li>
                        <span class="keyboard-shortcuts-keys"><kbd>Esc</kbd></span>
                        <span class="keyboard-shortcuts-action">Return to gallery</span>
                    </li>
                </ul>

                <h2>Source</h2>
                <ul class="source-list">
                    <li>
                        <a href="https://github.com/adamsimms/cloudberry" target="_blank" rel="noopener noreferrer" class="link">Cloudberry</a>
                        <span class="source-list-desc">Raspberry Pi field software — capture, upload, and power management</span>
                    </li>
                    <li>
                        <a href="https://github.com/adamsimms/pinchards.is" target="_blank" rel="noopener noreferrer" class="link">pinchards.is</a>
                        <span class="source-list-desc">This website and photograph archive</span>
                    </li>
                </ul>
            </div></div>
        </div>
    </div>

    <div class="contact_section" id="contact">
        <div class="container">
            <div class="row justify-content-center"><div class="col-12 col-md-10 col-lg-8">
                <h2>Contact</h2>
                <p><!--email_off--><a href="#" class="link" data-email-mailto>Email</a><!--/email_off--></p>
                <img src="images/info/boat.jpg" class="img-fluid info_img" alt="Boat approaching Pinchard's Island" width="1400" height="918" loading="lazy" decoding="async">
                <div class="copyright">
                    Copyright &copy; 2017&ndash;<?= $copyrightYear ?>
                </div>
            </div></div>
        </div>
    </div>

        </main>
    </div>

<?php pinchard_layout_footer([
    'extra_scripts' => <<<'JS'
    <script>
        (function() {
            var toc = document.querySelector('.info-toc');
            var nav = toc ? toc.querySelector('nav') : null;
            var hero = document.querySelector('.info-hero');
            var sectionIds = ['about', 'how', 'who', 'more', 'contact'];
            var hashAliases = {
                why: 'about',
                where: 'about',
                hardware: 'how',
                installation: 'how',
                citation: 'more',
                shortcuts: 'more'
            };
            var links = document.querySelectorAll('.info-toc nav a');
            var sections = sectionIds.map(function(id) {
                return document.getElementById(id);
            }).filter(Boolean);
            var mobileToc = window.matchMedia('(max-width: 991px)');
            var navigatingTo = null;
            var navigateUnlockTimer = null;

            function resolveSectionId(id) {
                return hashAliases[id] || id;
            }

            function setMobileTocVisible(visible) {
                if (!toc) {
                    return;
                }
                if (!mobileToc.matches) {
                    toc.classList.remove('is-visible');
                    return;
                }
                toc.classList.toggle('is-visible', visible);
            }

            if (toc && hero && 'IntersectionObserver' in window) {
                var heroObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        setMobileTocVisible(!entry.isIntersecting);
                    });
                }, {
                    root: null,
                    rootMargin: '-50px 0px 0px 0px',
                    threshold: 0
                });
                heroObserver.observe(hero);
                mobileToc.addEventListener('change', function() {
                    if (!mobileToc.matches) {
                        toc.classList.remove('is-visible');
                    }
                });
            }

            function scrollActiveLinkIntoView() {
                if (!nav || !mobileToc.matches) {
                    return;
                }
                var active = nav.querySelector('a.is-active');
                if (!active) {
                    return;
                }
                // Scroll only the TOC strip — never the document (scrollIntoView
                // on a fixed child can yank the page away from the section).
                var left = active.offsetLeft - (nav.clientWidth / 2) + (active.offsetWidth / 2);
                nav.scrollTo({ left: Math.max(0, left), behavior: 'smooth' });
            }

            function setActive(id) {
                links.forEach(function(link) {
                    var href = link.getAttribute('href') || '';
                    var match = href === '#' + id;
                    link.classList.toggle('is-active', match);
                    if (match) {
                        link.setAttribute('aria-current', 'true');
                    } else {
                        link.removeAttribute('aria-current');
                    }
                });
                scrollActiveLinkIntoView();
            }

            function lockNavigation(id) {
                navigatingTo = id;
                setActive(id);
                window.clearTimeout(navigateUnlockTimer);
                navigateUnlockTimer = window.setTimeout(function() {
                    navigatingTo = null;
                }, 1000);
            }

            function scrollToSection(id, behavior) {
                id = resolveSectionId(id);
                var target = document.getElementById(id);
                if (!target) {
                    return;
                }
                lockNavigation(id);
                if (window.history && window.history.pushState) {
                    window.history.pushState(null, '', '#' + id);
                } else {
                    window.location.hash = id;
                }
                target.scrollIntoView({
                    behavior: behavior || 'smooth',
                    block: 'start'
                });
                // Lazy images can still settle after the first jump; nudge once.
                window.setTimeout(function() {
                    if (navigatingTo !== id) {
                        return;
                    }
                    target.scrollIntoView({ behavior: 'auto', block: 'start' });
                    lockNavigation(id);
                }, 450);
            }

            if (!sections.length || !('IntersectionObserver' in window)) {
                links.forEach(function(link) {
                    link.addEventListener('click', function(event) {
                        var href = link.getAttribute('href') || '';
                        if (href.charAt(0) !== '#') {
                            return;
                        }
                        var id = href.slice(1);
                        if (!document.getElementById(resolveSectionId(id))) {
                            return;
                        }
                        event.preventDefault();
                        scrollToSection(id);
                    });
                });
                return;
            }

            // Prefer the section nearest the reading line so active state matches
            // what is actually on screen.
            var observer = new IntersectionObserver(function(entries) {
                if (navigatingTo) {
                    return;
                }
                var visible = entries
                    .filter(function(entry) { return entry.isIntersecting; })
                    .sort(function(a, b) {
                        return Math.abs(a.boundingClientRect.top) - Math.abs(b.boundingClientRect.top);
                    });
                if (visible.length) {
                    setActive(visible[0].target.id);
                }
            }, {
                root: null,
                rootMargin: '-20% 0px -55% 0px',
                threshold: 0
            });

            sections.forEach(function(section) {
                observer.observe(section);
            });

            function syncHashTarget() {
                var hash = resolveSectionId(window.location.hash.replace(/^#/, ''));
                if (!hash || !document.getElementById(hash)) {
                    return;
                }
                lockNavigation(hash);
            }

            links.forEach(function(link) {
                link.addEventListener('click', function(event) {
                    var href = link.getAttribute('href') || '';
                    if (href.charAt(0) !== '#') {
                        return;
                    }
                    var id = href.slice(1);
                    if (!document.getElementById(resolveSectionId(id))) {
                        return;
                    }
                    event.preventDefault();
                    scrollToSection(id);
                });
            });

            window.addEventListener('hashchange', syncHashTarget);
            if (window.location.hash) {
                window.requestAnimationFrame(function() {
                    var hash = resolveSectionId(window.location.hash.replace(/^#/, ''));
                    if (hash && document.getElementById(hash)) {
                        scrollToSection(hash, 'auto');
                    }
                });
            }
        })();
    </script>
JS,
]); ?>
