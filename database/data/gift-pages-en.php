<?php

/*
 * The English gift-idea pages (/en/gifts/...). Baku has plenty of people who
 * read English — expats, students, anyone sending a present home — and they
 * search in English. Each page answers one of those searches and is tied to
 * its Azerbaijani twin (`alt_of`), so search engines see one page in three
 * languages and the owner still picks the designs in a single place.
 */
return [
    [
        'slug' => 'birthday-gift',
        'alt_of' => 'ad-gunu',
        'menu_label' => 'Birthday',
        'link_text' => 'Birthday gift',
        'emoji' => '🎂',
        'title' => 'Birthday gift, chocolate with their photo',
        'meta_title' => 'Birthday gift in Baku, chocolate box with a photo | Nefis',
        'meta_description' => 'A birthday gift nobody else will bring: a chocolate box printed with the birthday person’s photo and your own words. Order online, delivered in Baku and across Azerbaijan.',
        'eyebrow' => 'Birthday gift',
        'intro' => 'A chocolate box carrying their photo, their name and your words, the kind of present that stays on the shelf long after the chocolate is gone.',
        'body' => <<<'MD'
## What to give for a birthday

Flowers wilt in a few days and an ordinary bar of chocolate is forgotten by the evening. Here you choose a design, upload a photo and write your greeting; we print it on the box and put the chocolate they like inside. Nobody sets this one aside with an “oh, sweets again”, the photo is looked at first.

## Who it suits

- **Someone you love**, a photo of the two of you and words only you two understand.
- **A child**, Kinder, Barbie or car designs with their own face on the pack.
- **A friend, a brother, a sister**, the joking ones: Netflix, Google, Spotify.
- **A parent or grandparent**, a family photo, printed properly.

## How to order

1. Pick a design below.
2. Upload the photo and type the name and greeting, you see the result on the page as you go.
3. Choose the chocolate that goes inside; add gift wrapping or a polaroid letter if you like.
4. Place the order: we bring it to the door in Baku and post it to the regions.

Birthday coming up fast? Order early, making and delivering usually takes 1–3 working days.
MD,
        'faq' => [
            ['q' => 'How long does it take?', 'a' => 'Usually 1–3 working days, delivery included. Every box is made by hand, so the sooner you order, the calmer it is for both of us.'],
            ['q' => 'Which photo works best?', 'a' => 'A sharp photo where the face is clearly visible. A portrait suits most designs; for family designs a picture of everyone together works well.'],
            ['q' => 'Can I write in English?', 'a' => 'Yes, in English, Azerbaijani or Russian. Whatever you type is printed exactly as written, so check the spelling before ordering.'],
        ],
    ],
    [
        'slug' => 'romantic-gift',
        'alt_of' => 'sevgiliye',
        'menu_label' => 'For your partner',
        'link_text' => 'Gift for the one you love',
        'emoji' => '❤️',
        'title' => 'A gift for the one you love, chocolate with your photo',
        'meta_title' => 'Romantic gift in Baku, photo chocolate box | Nefis',
        'meta_description' => 'A present for your girlfriend, boyfriend or spouse: a chocolate box with a photo of the two of you and the words you would rather write than say. Delivered in Baku.',
        'eyebrow' => 'For the one you love',
        'intro' => 'Your photo together on the box, and underneath it the sentence you have been meaning to say. Chocolate is eaten; the box tends to be kept.',
        'body' => <<<'MD'
## A gift that says it for you

Some things are easier written than said. On these boxes there is room for a real message, not a line on a card, but a few sentences of your own. Add the photo you both like and the design does the rest.

## Which design to choose

- **Love Story**, a “Special edition” look with your name in the heading and a long note below.
- **Love is…**, the bubble-gum style everyone grew up with, for something short and sweet.
- **Netflix, Spotify, Google**, for couples who would rather joke than be solemn.
- **I love you**, a repeating pattern with plenty of space for a confession.

## How it is made

You pick the design, upload the photo and type the text; the preview on the page is what gets printed. Inside goes the chocolate you choose. If you want it wrapped, we wrap it in patterned paper and tie a ribbon, and we can slip a polaroid letter inside the box.

For an anniversary or 14 February, order a couple of days ahead, those weeks are the busiest.
MD,
        'faq' => [
            ['q' => 'Can I keep it a surprise?', 'a' => 'Yes. Leave a note at checkout and the courier will not say what is inside, and we can leave the price out of the parcel.'],
            ['q' => 'How long can the text be?', 'a' => 'It depends on the design, some hold a few words, others a whole paragraph. The page shows the limit as you type.'],
            ['q' => 'Can I use two photos?', 'a' => 'Some designs have two photo slots. Look for the ones showing two frames in the catalogue below.'],
        ],
    ],
    [
        'slug' => 'valentines-day',
        'alt_of' => '14-fevral',
        'menu_label' => 'Valentine’s Day',
        'link_text' => 'Valentine’s Day gift',
        'emoji' => '💘',
        'title' => 'Valentine’s Day gift, chocolate with your photo',
        'meta_title' => 'Valentine’s Day gift in Baku, photo chocolate | Nefis',
        'meta_description' => 'A gift for 14 February: a chocolate box with a photo of the two of you and your own words. Order online in Baku, order early, February is busy.',
        'eyebrow' => '14 February',
        'intro' => 'On 14 February everyone brings the same flowers and the same heart-shaped box. This one has your photo on it and your words inside.',
        'body' => <<<'MD'
## Valentine’s Day, without the cliché

Roses and a generic box are safe and forgettable. A box with your own photograph and a message written in your own words is neither. It costs about the same as a bouquet and it does not have to be thrown away on the fourth day.

## Ready-made for 14 February

- **Love Story Vol 3**, two photos set like postage stamps, with a Valentine’s line.
- **I love you**, a repeating pattern and room for a real confession.
- **Love is…** in red, blue or orange, short, sweet, and instantly recognised.
- **Chocolate Puppin**, a photo of the two of you against flowing chocolate.

## Order in good time

February is the busiest fortnight of our year. A box takes 1–3 working days to make and deliver, so an order placed on the 13th is a gamble. Order by the 10th and you can choose the delivery day yourself at checkout.
MD,
        'faq' => [
            ['q' => 'Can you deliver exactly on 14 February?', 'a' => 'Yes, you pick the delivery day and the time slot at checkout, so it arrives on the day you want.'],
            ['q' => 'Do you deliver outside Baku?', 'a' => 'Yes, to every region of Azerbaijan by post. Allow a couple of extra days for the regions.'],
            ['q' => 'Can I add a letter?', 'a' => 'You can add a polaroid letter with a photo and a message, it goes inside the box.'],
        ],
    ],
    [
        'slug' => 'womens-day-8-march',
        'alt_of' => '8-mart',
        'menu_label' => '8 March',
        'link_text' => '8 March gift',
        'emoji' => '🌷',
        'title' => '8 March gift, chocolate with a photo',
        'meta_title' => '8 March gift in Baku, photo chocolate box | Nefis',
        'meta_description' => 'A gift for 8 March: a chocolate box with her photo and your words, for a mother, a wife, a sister or a colleague. Order online, delivered in Baku.',
        'eyebrow' => '8 March',
        'intro' => 'For 8 March, something that is clearly meant for her and not simply bought on the way home.',
        'body' => <<<'MD'
## What to give on 8 March

Flowers are expected, which is exactly the problem: by the evening of 8 March every desk in Baku has the same bouquet on it. A box with her photograph, her name and a few words of yours is a different kind of attention, and it keeps.

## For whom

- **Mother**, the “Alyonka” designs with kelaghayi and carpet patterns, or a family photo.
- **Wife or girlfriend**, Love Story, Milka, Love is…
- **Sister or friend**, the lighter, funnier designs.
- **Colleagues**, several boxes at once; for ten or more, write to us and we will price it properly.

## How to order

Choose a design, upload the photo, write the greeting, pick the chocolate. In Baku we deliver to the door on the day you choose; to the regions we send by post. Make the order a few days before 8 March, the first week of March is our busiest.
MD,
        'faq' => [
            ['q' => 'Can I order several at once?', 'a' => 'Yes. For ten boxes or more get in touch on WhatsApp, corporate orders have their own price and we agree the deadline in advance.'],
            ['q' => 'What if I have no photo of her?', 'a' => 'Some designs are made for words alone, with no photo slot. Look for those in the catalogue below.'],
            ['q' => 'Is gift wrapping available?', 'a' => 'Yes, patterned paper and a ribbon, chosen while you are personalising the design.'],
        ],
    ],
    [
        'slug' => 'gift-for-mum',
        'alt_of' => 'anaya',
        'menu_label' => 'For mum',
        'link_text' => 'Gift for a mother',
        'emoji' => '💐',
        'title' => 'A gift for mum, chocolate with a family photo',
        'meta_title' => 'Gift for a mother in Baku, photo chocolate box | Nefis',
        'meta_description' => 'A present for a mother: a chocolate box with a family photo, her name and your words. Made by hand in Baku, delivered across Azerbaijan.',
        'eyebrow' => 'For a mother',
        'intro' => 'Mothers keep everything their children give them. This is worth keeping: a photo of you together, printed properly, with words she can read again.',
        'body' => <<<'MD'
## What mothers actually keep

Ask anyone: the drawer holds the drawings, the cards, the photographs. Not the flowers. A chocolate box with a family photograph and a line from you belongs in that drawer, and the chocolate inside is simply the first part of the gift.

## Designs that suit

- **Alyonka in Azerbaijani style**, kelaghayi and carpet patterns around her portrait.
- **Family Frame**, everyone in one photo, in a frame of national ornament.
- **Frame Player**, a photo like an Instagram post, with a song underneath.
- Any of the simpler designs, if you would rather the words did the work.

## Ordering

Pick the design, upload a photo where her face is clear, type the greeting, choose the chocolate. Delivery in Baku is to the door, on the day you choose; to the regions, by post. Making and delivering usually takes 1–3 working days.
MD,
        'faq' => [
            ['q' => 'Which photo should I choose?', 'a' => 'A sharp one where the face is well lit and takes up a good part of the frame. Old photos work if they are scanned clearly.'],
            ['q' => 'Can I write in Russian or Azerbaijani?', 'a' => 'Yes, and you can mix them. What you type is printed exactly as it is.'],
            ['q' => 'Can it be delivered to her address?', 'a' => 'Of course, enter her address and phone at checkout, and add a note if it should be a surprise.'],
        ],
    ],
    [
        'slug' => 'corporate-gifts',
        'alt_of' => 'korporativ',
        'menu_label' => 'Corporate',
        'link_text' => 'Corporate gifts',
        'emoji' => '🏢',
        'title' => 'Corporate gifts, chocolate with your logo',
        'meta_title' => 'Corporate gifts in Baku, branded chocolate boxes | Nefis',
        'meta_description' => 'Chocolate boxes for a company: your logo, your colours, a photo of the team or a word for each employee. Made by hand in Baku, delivered across Azerbaijan.',
        'eyebrow' => 'For companies',
        'intro' => 'Branded chocolate for clients, partners and your own team, with your logo on the box, and a name on each one if you want it.',
        'body' => <<<'MD'
## What a corporate order looks like

A company usually needs one of three things: a present for clients at the New Year, something for the team on a professional holiday, or a small thank-you handed out at a conference. All three work the same way: your logo and colours on the box, and the chocolate you choose inside.

## What we can do

- **Your logo and brand colours** on every box.
- **A name on each box**, for a team, that detail is what people remember.
- **A photo of the team or the office**, printed properly.
- **Any quantity**, from ten boxes upwards.

## How to agree it

Write to us on WhatsApp with the number of boxes and the date. We show a preview before printing anything, the price depends on the quantity, and larger runs need a few days more than a single box. Invoices and bank transfer are no problem.
MD,
        'faq' => [
            ['q' => 'What is the smallest corporate order?', 'a' => 'Ten boxes. Below that, order them one by one on the site, the price is the same as a normal box.'],
            ['q' => 'How long does a large order take?', 'a' => 'A hundred boxes need about a week. Tell us the date and we will say straight away whether it can be met.'],
            ['q' => 'Can we pay by bank transfer?', 'a' => 'Yes. Write to us and we will send an invoice and the documents your accounting needs.'],
        ],
    ],
    [
        'slug' => 'photo-chocolate',
        'alt_of' => 'sekilli-sokolad',
        'menu_label' => 'Photo chocolate',
        'link_text' => 'Chocolate with a photo',
        'emoji' => '🍫',
        'title' => 'Chocolate with a photo, how it is made',
        'meta_title' => 'Chocolate with a photo in Baku, personalised boxes | Nefis',
        'meta_description' => 'How a photo chocolate box is made: choose a design, upload a photo, add your words, pick the chocolate. Made by hand in Baku, delivered across Azerbaijan.',
        'eyebrow' => 'Photo chocolate',
        'intro' => 'The photo is printed on the box, not on the chocolate, which is why it stays sharp, and why the chocolate inside is the one you actually like.',
        'body' => <<<'MD'
## How it works

1. **Choose a design.** Each one is a ready-made template with places for your photo and your words.
2. **Upload the photo.** You see it on the box straight away and can move and zoom it.
3. **Write the words.** A name, a date, a greeting, as long as the design allows.
4. **Pick the chocolate.** Milka, Alpen Gold, Kinder and the rest; the bar goes inside the box.
5. **Order.** In Baku we deliver to the door on the day you choose; to the regions, by post.

## What a good photo looks like

Sharp, well lit, with the face taking up a decent part of the frame and a simple background. A photo taken against a window, or one photographed off a screen, prints badly. There is a small guide next to the upload button showing what works and what does not.

## What else can go with it

Gift wrapping with a ribbon, a polaroid letter inside the box, and a live photo: a QR code on the box that plays your video over the picture when a phone is held above it.
MD,
        'faq' => [
            ['q' => 'Is the photo printed on the chocolate itself?', 'a' => 'No, on the box. The chocolate inside is a normal sealed bar, and you choose which one.'],
            ['q' => 'How long does it keep?', 'a' => 'As long as the chocolate inside: its own date is on the bar. The box itself keeps indefinitely.'],
            ['q' => 'What resolution should the photo be?', 'a' => 'Anything from a modern phone is fine. Screenshots and photos of screens are the ones that disappoint.'],
        ],
    ],
];
