// Jest setup

// Mock expo-router
jest.mock('expo-router', () => ({
    useRouter: () => ({
        push: jest.fn(),
        replace: jest.fn(),
        back: jest.fn(),
    }),
    useSegments: () => ['(tabs)'],
    Link: 'Link',
    router: {
        push: jest.fn(),
        replace: jest.fn(),
        back: jest.fn(),
    }
}));

// Mock secure store
jest.mock('expo-secure-store', () => ({
    getItemAsync: jest.fn(),
    setItemAsync: jest.fn(),
    deleteItemAsync: jest.fn(),
}));
