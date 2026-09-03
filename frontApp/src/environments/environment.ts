// export const environment = {
//   production: false,
//   // baseUrl: 'http://localhost:8000', 
//   // apiUrl: 'http://localhost:8000/api/v1',
//   baseUrl: 'http://127.0.0.1:8000', 

//   apiUrl:'http://127.0.0.1:8000/api/v1',
//   pusherKey: 'd9606d876f1194c14d8c',
//   pusherCluster: 'eu',
//   pusherHost: 'api-eu.pusher.com',
//   pusherPort: 443,
// };

export const environment = {
  production: true,
  // Remplace par ton vrai domaine (http ou https selon ton certificat SSL)
  apiUrl: 'https://gestion.cabinformatique.com/api/v1',
  baseUrl: 'https://gestion.cabinformatique.com',
  pusherKey: 'd9606d876f1194c14d8c',
  pusherCluster: 'eu',
  pusherHost: 'api-eu.pusher.com',
  pusherPort: 443,
};
 