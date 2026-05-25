import PenSquare from 'lucide-react/icons/square-pen';
import { motion } from 'framer-motion';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui';

interface MobileFABProps {
  onPress: () => void;
}

export function MobileFAB({ onPress }: MobileFABProps) {
  const { t } = useTranslation('feed');

  return (
    <motion.div
      className="fixed bottom-6 right-6 z-40 md:hidden"
      initial={{ scale: 0, opacity: 0 }}
      animate={{ scale: 1, opacity: 1 }}
      transition={{ type: 'spring', stiffness: 260, damping: 20, delay: 0.3 }}
    >
      <Button
        isIconOnly
        className="w-14 h-14 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/30"
        onPress={onPress}
        aria-label={t('compose.create_post')}
      >
        <PenSquare className="w-5 h-5" />
      </Button>
    </motion.div>
  );
}

export default MobileFAB;
