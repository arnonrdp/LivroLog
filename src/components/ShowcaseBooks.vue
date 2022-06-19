<template>
  <section class="flex content-start justify-around">
    <figure v-for="book in books" :key="book.id">
      <img :src="book.thumbnail" :alt="$t('book.cover-image-alt', [book.title])" />
    </figure>
  </section>
</template>

<script setup lang="ts">
const books = [
  {
    id: 1,
    title: 'By Order of the Peaky Blinders',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51sCiLtj6wL._SX373_BO1,204,203,200_.jpg'
  },
  {
    id: 2,
    title: 'Peaky Blinders: The Real Story',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51z-Xjb-FGL._SX324_BO1,204,203,200_.jpg'
  },
  {
    id: 3,
    title: 'É Assim que Acaba',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51i7kH+rh9L._SX322_BO1,204,203,200_.jpg'
  },
  {
    id: 4,
    title: 'Tokyo Revengers',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51agJBMZyQL._SX328_BO1,204,203,200_.jpg'
  },
  {
    id: 5,
    title: 'A garota do lago',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/516VOgxwg2L._SX339_BO1,204,203,200_.jpg'
  },
  {
    id: 6,
    title: 'Mais esperto que o Diabo: O mistério revelado da liberdade e do sucesso',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51DzotPWS0L._SX331_BO1,204,203,200_.jpg'
  },
  {
    id: 7,
    title: 'O homem mais rico da Babilônia',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51cB4wSNxQL._SX333_BO1,204,203,200_.jpg'
  },
  {
    id: 8,
    title: '12 regras para a vida: Um antídoto para o caos',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41XbfSiYscL._SX348_BO1,204,203,200_.jpg'
  },
  {
    id: 9,
    title: 'Por que fazemos o que fazemos?',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51xH0mWSeGL._SX331_BO1,204,203,200_.jpg'
  },
  {
    id: 10,
    title: 'Verity',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51ABRx3d7GL._SX335_BO1,204,203,200_.jpg'
  },
  {
    id: 11,
    title: 'Até o verão terminar',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51ccTKC7y1L._SX324_BO1,204,203,200_.jpg'
  },
  {
    id: 12,
    title: 'As cinco linguagens do amor - 3 edição: Como expressar um compromisso de amor a seu cônjuge',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/412lekY6fJL._SX325_BO1,204,203,200_.jpg'
  },
  {
    id: 13,
    title: 'Quem pensa enriquece: O Legado',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41ro37po+CL._SX331_BO1,204,203,200_.jpg'
  },
  {
    id: 14,
    title: 'Heartstopper: Dois garotos, um encontro (vol. 1)',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51wx3A+5s6L._SX347_BO1,204,203,200_.jpg'
  },
  {
    id: 15,
    title: 'Mulheres que correm com os lobos',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41ogXvQ5WYL._SX341_BO1,204,203,200_.jpg'
  },
  {
    id: 16,
    title: 'Mindset: A nova psicologia do sucesso',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41suUFbw-eL._SX346_BO1,204,203,200_.jpg'
  },
  {
    id: 17,
    title: 'Novembro, 9',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51-A+gK197L._SX320_BO1,204,203,200_.jpg'
  },
  {
    id: 18,
    title: 'Os segredos da mente milionária',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51+GR0xBMWL._SX331_BO1,204,203,200_.jpg'
  },
  {
    id: 19,
    title: 'Vermelho, branco e sangue azul',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41QaXCahKrL._SX345_BO1,204,203,200_.jpg'
  },
  {
    id: 20,
    title: 'Por lugares incríveis',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/510HCDDGb3L._SX331_BO1,204,203,200_.jpg'
  },
  {
    id: 21,
    title: 'Akira - Vol. 1',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51-PYkPl3cL._SX343_BO1,204,203,200_.jpg'
  },
  {
    id: 22,
    title: 'Do Mil ao Milhão. Sem Cortar o Cafezinho',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41TsvI70n9L._SX335_BO1,204,203,200_.jpg'
  },
  {
    id: 23,
    title: 'Trono de Vidro: Reino de Cinzas (Vol. 6)',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41wtJAe8QDL._SX335_BO1,204,203,200_.jpg'
  },
  {
    id: 24,
    title: 'Os sete maridos de Evelyn Hugo',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41xVhz5sL1L._SX346_BO1,204,203,200_.jpg'
  },
  {
    id: 25,
    title: 'Hábitos atômicos: Um método fácil e comprovado de criar bons hábitos e se livrar dos maus',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51DOkmV31rL._SX353_BO1,204,203,200_.jpg'
  },
  {
    id: 26,
    title: 'O poder do hábito',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51Xby92J9KL._SX346_BO1,204,203,200_.jpg'
  },
  {
    id: 27,
    title: 'Especialista em pessoas: Soluções bíblicas e inteligentes para lidar com todo tipo de gente',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51Ysje72xQL._SX340_BO1,204,203,200_.jpg'
  },
  {
    id: 28,
    title: 'Como fazer amigos e influenciar pessoas',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41tkOqnrFNL._SX346_BO1,204,203,200_.jpg'
  },
  {
    id: 29,
    title: 'O Cérebro da Criança',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41ZcBm3z02L._SX336_BO1,204,203,200_.jpg'
  },
  {
    id: 30,
    title: 'Escravidão - Volume 3: Da Independência do Brasil à Lei Áurea',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51KySEk1XyL._SX338_BO1,204,203,200_.jpg'
  },
  {
    id: 31,
    title: 'As Crônicas de Nárnia. Brochura',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51+2QAB7I+L._SX329_BO1,204,203,200_.jpg'
  },
  {
    id: 32,
    title: 'Tudo é rio',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41Od9BeoEhL._SX335_BO1,204,203,200_.jpg'
  },
  {
    id: 33,
    title: 'Todas as suas (im)perfeições',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51is1KDs5zL._SX337_BO1,204,203,200_.jpg'
  },
  {
    id: 34,
    title: 'A Culpa é Das Estrelas',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41CXzLy05EL._SX334_BO1,204,203,200_.jpg'
  },
  {
    id: 35,
    title: 'Boa Noite Punpun - Vol. 5',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41hkiBovdeL._SX343_BO1,204,203,200_.jpg'
  },
  {
    id: 36,
    title: 'Perigoso!',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/517ZzYgUcaL._SX434_BO1,204,203,200_.jpg'
  },
  {
    id: 37,
    title: 'A coragem de ser imperfeito: Como aceitar a própria vulnerabilidade, vencer a vergonha e ousar ser quem você é',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41BT0OP-5GL._SX346_BO1,204,203,200_.jpg'
  },
  {
    id: 38,
    title: 'A revolução dos bichos: Um conto de fadas',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/61owA5ey3iL._SX324_BO1,204,203,200_.jpg'
  },
  {
    id: 39,
    title: 'Torto arado',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41fQpp33yUL._SX327_BO1,204,203,200_.jpg'
  },
  {
    id: 40,
    title: 'O casal que mora ao lado',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41upASGsAmL._SX335_BO1,204,203,200_.jpg'
  },
  {
    id: 41,
    title: 'O lado feio do amor',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51RT++oGS6L._SX322_BO1,204,203,200_.jpg'
  },
  {
    id: 42,
    title: 'Heartstopper: Um passo adiante (vol. 3)',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51SUDnygEUL._SX347_BO1,204,203,200_.jpg'
  },
  {
    id: 43,
    title: 'Leitura de verão',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41bRdJEhzfL._SX342_BO1,204,203,200_.jpg'
  },
  {
    id: 44,
    title: 'Where the Crawdads Sing',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41rzRPDRxJL._SX331_BO1,204,203,200_.jpg'
  },
  {
    id: 45,
    title: 'Battle for the American Mind: Uprooting a Century of Miseducation',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41TAXKY0vaL._SX329_BO1,204,203,200_.jpg'
  },
  {
    id: 46,
    title: "It's Not Summer Without You",
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51ky4TIb1bL._SX331_BO1,204,203,200_.jpg'
  },
  {
    id: 47,
    title: 'It Ends with Us: A Novel',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51zhNb8Ei4L._SX320_BO1,204,203,200_.jpg'
  },
  {
    id: 48,
    title: 'Why a Daughter Needs a Dad',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51MfovVHFBL._SY498_BO1,204,203,200_.jpg'
  },
  {
    id: 49,
    title: 'The Summer I Turned Pretty',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41-KzR9ASUL._SX331_BO1,204,203,200_.jpg'
  },
  {
    id: 50,
    title: "We'll Always Have Summer",
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41ju3aS6WVS._SX331_BO1,204,203,200_.jpg'
  },
  {
    id: 51,
    title: 'Ugly Love: A Novel',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41g9RfNVZBL._SX320_BO1,204,203,200_.jpg'
  },
  {
    id: 52,
    title: 'The Seven Husbands of Evelyn Hugo: A Novel',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41u+GBPJQXL._SX320_BO1,204,203,200_.jpg'
  },
  {
    id: 53,
    title: 'Reminders of Him: A Novel',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41n9-p6-PpL._SX331_BO1,204,203,200_.jpg'
  },
  {
    id: 54,
    title: 'Atomic Habits: An Easy & Proven Way to Build Good Habits & Break Bad Ones',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51-uspgqWIL._SX329_BO1,204,203,200_.jpg'
  },
  {
    id: 55,
    title: 'Dad Jokes',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41LD+nVemVL._SX312_BO1,204,203,200_.jpg'
  },
  {
    id: 56,
    title: 'The Hotel Nantucket',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41D9XenJeVL._SX320_BO1,204,203,200_.jpg'
  },
  {
    id: 57,
    title: 'The Body Keeps the Score: Brain, Mind, and Body in the Healing of Trauma',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41D3enj6JVS._SX324_BO1,204,203,200_.jpg'
  },
  {
    id: 58,
    title: 'Every Summer After',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41Tb9TLd5UL._SX331_BO1,204,203,200_.jpg'
  },
  {
    id: 59,
    title: 'My First Learn-to-Write Workbook',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51ABc0hukNL._SX384_BO1,204,203,200_.jpg'
  },
  {
    id: 60,
    title: 'Os Quatro Acordos: A Practical Guide to Personal Liberdade De Don Miguel Ruiz',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51EA+bXwJsL._SX343_BO1,204,203,200_.jpg'
  },
  {
    id: 61,
    title: 'People We Meet on Vacation',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/416m2cCJV9L._SX324_BO1,204,203,200_.jpg'
  },
  {
    id: 62,
    title: "Fathers Day Gifts: Dad Tell Me Your Story: A Father's Guided Journal to Share His Life and His Love",
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41XVjX0HMGL._SX348_BO1,204,203,200_.jpg'
  },
  {
    id: 63,
    title: "Oh, the Places You'll Go!",
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51x8pmqjY0L._SX376_BO1,204,203,200_.jpg'
  },
  {
    id: 64,
    title: 'Sparring Partners (Jake Brigance)',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41HE+g156iL._SX327_BO1,204,203,200_.jpg'
  },
  {
    id: 65,
    title: 'Nova o 48 Leis do Poder',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41KY-NORo9L._SX355_BO1,204,203,200_.jpg'
  },
  {
    id: 66,
    title: 'The Very Hungry Caterpillar',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41lkouDhm5L._SY354_BO1,204,203,200_.jpg'
  },
  {
    id: 67,
    title: 'Beach Read',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41szdIaNv9L._SX331_BO1,204,203,200_.jpg'
  },
  {
    id: 68,
    title: 'The Last Thing He Told Me: A Novel',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51aJ7twCu0L._SX327_BO1,204,203,200_.jpg'
  },
  {
    id: 69,
    title: 'The Power of One More: The Ultimate Guide to Happiness and Success',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41EwHiQzu2L._SX336_BO1,204,203,200_.jpg'
  },
  {
    id: 70,
    title: 'All Your Perfects: A Novel',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41GVW9gyGtL._SX318_BO1,204,203,200_.jpg'
  },
  {
    id: 71,
    title: 'Book Lovers',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41W5Iq70wrL._SX330_BO1,204,203,200_.jpg'
  },
  {
    id: 72,
    title: 'November 9: A Novel',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41HB7HlN27L._SX320_BO1,204,203,200_.jpg'
  },
  {
    id: 73,
    title: "I'd Like to Play Alone, Please: Essays",
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51-1dSKGZ+L._SX338_BO1,204,203,200_.jpg'
  },
  {
    id: 74,
    title: 'The Midnight Library: A Novel',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41ATfFjhelL._SX329_BO1,204,203,200_.jpg'
  },
  {
    id: 75,
    title: 'How to Win Friends & Influence People',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41FYFMn7lZL._SX320_BO1,204,203,200_.jpg'
  },
  {
    id: 76,
    title: 'Heart Bones',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51fK9K+JzzL._SX316_BO1,204,203,200_.jpg'
  },
  {
    id: 77,
    title: 'It Starts with Us',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51b3jkk15SL._SX320_BO1,204,203,200_.jpg'
  },
  {
    id: 78,
    title: 'The Alchemist, 25th Anniversary: A Fable About Following Your Dream',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51kcX5PpaZL._SX329_BO1,204,203,200_.jpg'
  },
  {
    id: 79,
    title: 'I Love You to the Moon and Back',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/51p2SDOCV9L._SX482_BO1,204,203,200_.jpg'
  },
  {
    id: 80,
    title: 'I Love Dad with The Very Hungry Caterpillar',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/41G63Ek+mML._SX410_BO1,204,203,200_.jpg'
  }
]

for (let i = books.length - 1; i > 0; i--) {
  const j = Math.floor(Math.random() * (i + 1))
  ;[books[i], books[j]] = [books[j], books[i]]
}
</script>

<style scoped>
section {
  background-image: url('~@/assets/shelfcenter.jpg');
  background-repeat: repeat;
  border-radius: 6px;

  height: 100vh;
  min-height: 302px;
}

section figure {
  align-items: flex-end;
  display: flex;
  height: 143.5px;
  margin: 0 1.5rem;
  max-width: 80px;
  position: relative;
  z-index: 0;
}

img {
  height: 115px;
}
</style>
