<template>
  <section class="flex justify-around">
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
    title: 'Refatoração: Aperfeiçoando o Design de Códigos Existentes',
    thumbnail: 'https://images-na.ssl-images-amazon.com/images/I/4125lRe2M9L._SX347_BO1,204,203,200_.jpg'
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
  }
]

for (let i = books.length - 1; i > 0; i--) {
  const j = Math.floor(Math.random() * (i + 1))
  ;[books[i], books[j]] = [books[j], books[i]]
}
</script>

<style scoped>
section > figure {
  height: 14rem;
  margin: 0 1.5rem;
}

section > figure > img {
  height: 11rem;
}
</style>
