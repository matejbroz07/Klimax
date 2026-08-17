import re

with open('reference.html', 'r', encoding='utf-8') as f:
    c = f.read()

# Fix image wrapper
c = re.sub(
    r'<img src="([^"]+)" alt="([^"]+)" class="client-card__bg">',
    r'<div class="client-card__image-wrapper">\n                            <img src="\1" alt="\2" class="client-card__image" loading="lazy">\n                        </div>',
    c
)

# Fix brand logo
c = re.sub(
    r'<div class="client-card__logo">\s*<img src="([^"]+)" alt="([^"]+)">\s*</div>',
    r'<div class="client-card__brand">\n                                <span class="client-card__brand-label">Využívá:</span>\n                                <img src="\1" alt="\2" class="client-card__brand-logo">\n                            </div>',
    c
)

with open('reference.html', 'w', encoding='utf-8') as f:
    f.write(c)

print("reference.html updated successfully!")
