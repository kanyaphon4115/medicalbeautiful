<section class="hospital-section doctors-section container" id="doctors" aria-labelledby="doctors-title" data-no-translate lang="en">
    <div class="section-heading"><div><p class="eyebrow">MEET OUR DOCTORS</p><h2 id="doctors-title">Expert Doctors, Exceptional Care</h2><p>Meet our experienced and trusted doctors across partner hospitals.</p></div><a class="text-link" href="#doctors/all">View all doctors <x-icon name="arrow" size="16"/></a></div>
    <div class="demo-note"><span></span> Demo collection · All doctors, specialties, qualifications, and experience below are fictional.</div>
    <div class="hospital-shell"><button type="button" class="carousel-arrow hospital-prev" data-slide="doctor-track" data-direction="-1" aria-label="Previous doctors"><x-icon name="chevron"/></button><div class="hospital-track" id="doctor-track" aria-label="Demo doctors"></div><button type="button" class="carousel-arrow hospital-next" data-slide="doctor-track" data-direction="1" aria-label="Next doctors"><x-icon name="chevron"/></button></div>
    <noscript><p>Please enable JavaScript to explore the fictional doctor profiles.</p></noscript>
</section>
<template id="doctor-card-template">
    <article class="hospital-card doctor-carousel-card" data-no-translate lang="en">
        <div class="hospital-image"><div data-doctor-portrait></div><span class="demo-badge">DEMO</span><button class="favorite" type="button" data-doctor-favorite aria-pressed="false"><x-icon name="heart" size="22"/></button></div>
        <div class="hospital-body"><h3><a data-doctor-name data-doctor-details></a></h3><p class="doctor-specialty" data-doctor-specialty></p><a class="location doctor-hospital" data-doctor-hospital><x-icon name="document" size="14"/><span></span></a><p class="location doctor-experience"><x-icon name="document" size="14"/><span data-doctor-experience></span></p></div>
    </article>
</template>
